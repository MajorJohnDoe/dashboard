<?php
namespace Dashboard\Stickynote;

use Dashboard\Core\Interfaces\DatabaseInterface;

class StickyCategory {
    private $id;
    private $title;
    private $color;
    private $db;

    public function __construct(DatabaseInterface $db) {
        $this->db = $db;
    }

    public function create($user_id, $title, $color) {
        $query = "INSERT INTO sticky_categories (user_id, title, color) VALUES (?, ?, ?)";
        $result = $this->db->q($query, "iss", $user_id, $title, $color);
        return $result ? $this->db->lastInsertId() : false;
    }

    public function edit($category_id, $user_id, $title, $color) {
        $query = "UPDATE sticky_categories SET title = ?, color = ? WHERE id = ? AND user_id = ?";
        return $this->db->q($query, "ssii", $title, $color, $category_id, $user_id);
    }

    public function delete(int $category_id, int $user_id, $move_to_category_id = null) {
        $this->db->beginTransaction();

        if ($move_to_category_id) {
            $move_query = "UPDATE sticky_notes SET category_id = ? WHERE category_id = ? AND user_id = ?";
            $this->db->q($move_query, "iii", $move_to_category_id, $category_id, $user_id);
        } else {
            $delete_notes_query = "DELETE FROM sticky_notes WHERE category_id = ? AND user_id = ?";
            $check = $this->db->q($delete_notes_query, "ii", $category_id, $user_id);
        }

        $delete_category_query = "DELETE FROM sticky_categories WHERE id = ? AND user_id = ?";
        $result = $this->db->q($delete_category_query, "ii", $category_id, $user_id);

        if ($result) {
            $this->db->commit();
            return true;
        } else {
            $this->db->rollback();
            return $check;
        }
    }

    public function categoryData(int $category_id, int $user_id): array {
        $query = "SELECT * FROM `sticky_categories` WHERE `user_id` = ? AND `id` = ? LIMIT 1";
        return $this->db->q($query, "ii", $user_id, $category_id);
    }

    public function getNbrCategories(int $user_id): int {
        $query = "SELECT COUNT(`id`) AS nbrCategories FROM `sticky_categories` WHERE `user_id` = ? LIMIT 1";
        $result = $this->db->q($query, "i", $user_id);
        return $result[0]['nbrCategories'] ?? 0;
    }

    public function getAll($user_id) {
        $query = "SELECT * FROM sticky_categories WHERE user_id = ? ORDER BY title";
        return $this->db->q($query, "i", $user_id);
    }
}
?>