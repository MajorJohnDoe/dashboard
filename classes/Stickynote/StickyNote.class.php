<?php
namespace Dashboard\Stickynote;

use Dashboard\Core\Interfaces\DatabaseInterface;

class StickyNote {
    private $db;

    public function __construct(DatabaseInterface $db) {
        $this->db = $db;
    }

    public function create($user_id, $category_id, $title, $content, $file_path = null, $is_pinned = false) {
        $query = "INSERT INTO sticky_notes (user_id, category_id, title, content, file_path, is_pinned, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $result = $this->db->q($query, "iisssi", $user_id, $category_id ?? null, $title, $content, $file_path, $is_pinned);
        return $result ? $this->db->lastInsertId() : false;
    }

    public function delete($note_id, $user_id) {
        $query = "DELETE FROM sticky_notes WHERE id = ? AND user_id = ?";
        $result = $this->db->q($query, "ii", $note_id, $user_id);
        
        if ($result) {
            // If note deletion was successful, delete the image records
            $this->deleteNoteImages($note_id);
        }
        
        return $result;
    }

    public function edit($note_id, $user_id, $title, $content, $file_path = null, $is_pinned = false) {
        $query = "UPDATE sticky_notes SET title = ?, content = ?, file_path = ?, is_pinned = ?, updated_at = NOW() 
                  WHERE id = ? AND user_id = ?";
        return $this->db->q($query, "sssiii", $title, $content, $file_path, $is_pinned, $note_id, $user_id);
    }

    public function move($note_id, $user_id, $new_category_id) {
        $query = "UPDATE sticky_notes SET category_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
        return $this->db->q($query, "iii", $new_category_id, $note_id, $user_id);
    }

    public function updateNoteContent($note_id, $content) {
        $query = "UPDATE sticky_notes SET content = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->q($query, "si", $content, $note_id);
    }

    public function duplicate($note_id, $user_id) {
        $query = "INSERT INTO sticky_notes (user_id, category_id, title, content, file_path, is_pinned, created_at, updated_at)
                  SELECT user_id, category_id, CONCAT(title, ' (Copy)'), content, file_path, is_pinned, NOW(), NOW()
                  FROM sticky_notes WHERE id = ? AND user_id = ?";
        $result = $this->db->q($query, "ii", $note_id, $user_id);
        return $result ? $this->db->lastInsertId() : false;
    }

    public function getCategories($user_id) {
        // Query 1: Get categories
        $categoryQuery = "
            SELECT id, title, color
            FROM (
                SELECT NULL as id, 'Uncategorized' as title, '#ccc' as color
                UNION ALL
                SELECT id, title, color FROM sticky_categories WHERE user_id = ?
            ) c
            ORDER BY CASE WHEN id IS NULL THEN 1 ELSE 0 END, title ASC
        ";
        $categories = $this->db->q($categoryQuery, "i", $user_id);

        // Query 2: Get note counts
        $countQuery = "
            SELECT 
                COALESCE(category_id, 'uncategorized') as category_id, 
                COUNT(*) as note_count
            FROM sticky_notes
            WHERE user_id = ?
            GROUP BY COALESCE(category_id, 'uncategorized')
        ";
        $noteCounts = $this->db->q($countQuery, "i", $user_id);

        // Combine the results
        $noteCountMap = [];
        foreach ($noteCounts as $count) {
            $noteCountMap[$count['category_id']] = $count['note_count'];
        }

        $result = [];
        foreach ($categories as $category) {
            $categoryId = $category['id'] ?? 'uncategorized';
            $category['note_count'] = $noteCountMap[$categoryId] ?? 0;
            $result[] = $category;
        }

        return $result;
    }

    public function getNoteData($note_id, $user_id) {
        $query = "SELECT n.*, c.title as category_title, c.color as category_color 
                  FROM sticky_notes n 
                  LEFT JOIN sticky_categories c ON n.category_id = c.id 
                  WHERE n.id = ? AND n.user_id = ? 
                  LIMIT 1";
        
        $result = $this->db->q($query, "ii", $note_id, $user_id);
        return $result ? $result[0] : null;
    }

    public function getNotes($user_id, $limit = 100, $category_id = null, $content_preview_length = 150) {
        $params = [$user_id];
        $types = "i";
    
        $base_query = "SELECT n.id, 
                              n.category_id, 
                              n.title, 
                              n.content,
                              n.file_path, 
                              n.is_pinned, 
                              n.created_at, 
                              n.updated_at,
                              c.title as category_title, 
                              c.color as category_color 
                       FROM 
                              sticky_notes n 
                       LEFT JOIN sticky_categories c 
                              ON n.category_id = c.id 
                       WHERE n.user_id = ?";
    
        if ($category_id !== null) {
            if ($category_id === 0) {
                $query = $base_query . " AND n.category_id IS NULL 
                          ORDER BY n.created_at DESC";
            } else {
                $query = $base_query . " AND n.category_id = ? 
                          ORDER BY n.created_at DESC";
                $params[] = $category_id;
                $types .= "i";
            }
        } else {
            $query = $base_query . " ORDER BY n.created_at DESC";
        }
    
        $query .= " LIMIT " . intval($limit);
    
        $result = $this->db->q($query, $types, ...$params);
    
        if ($result) {
            foreach ($result as &$note) {
                $note['content'] = $this->generatePreview($note['content'], $content_preview_length);
            }
        }
    
        return $result ?: [];
    }

    private function generatePreview($content, $length = 150) {
        // Remove HTML tags
        $text = strip_tags($content);
        
        // Trim whitespace
        $text = trim($text);
        
        // If the text is shorter than the preview length, return it as is
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        // Find the last space within the preview length
        $lastSpace = mb_strrpos(mb_substr($text, 0, $length), ' ');
        
        // If there's no space, just cut at the preview length
        if ($lastSpace === false) {
            $preview = mb_substr($text, 0, $length);
        } else {
            $preview = mb_substr($text, 0, $lastSpace);
        }
        
        // Add ellipsis
        return $preview . '...';
    }    

/*     public function search($user_id, $search_term, $category_id = null) {
        $query = "SELECT n.*, c.title as category_title, c.color as category_color 
                  FROM sticky_notes n 
                  LEFT JOIN sticky_categories c ON n.category_id = c.id 
                  WHERE n.user_id = ? AND (n.title LIKE ? OR n.content LIKE ?)";
        $params = [$user_id, "%$search_term%", "%$search_term%"];
        $types = "iss";
    
        if ($category_id !== null) {
            $query .= " AND n.category_id " . ($category_id === 0 ? "IS NULL" : "= ?");
            if ($category_id !== 0) {
                $params[] = $category_id;
                $types .= "i";
            }
        }
    
        $query .= " ORDER BY n.created_at DESC";
    
        return $this->db->q($query, $types, ...$params);
    } */

    public function search($user_id, $search_term, $category_id = null, $content_preview_length = 150) {
        // First, ensure you have a FULLTEXT index on your table
        // You can add it with: ALTER TABLE sticky_notes ADD FULLTEXT(title, content);
    
        $query = "SELECT n.*, c.title as category_title, c.color as category_color 
                  FROM sticky_notes n 
                  LEFT JOIN sticky_categories c ON n.category_id = c.id 
                  WHERE n.user_id = ? AND MATCH(n.title, n.content) AGAINST(? IN BOOLEAN MODE)";
        $params = [$user_id, $search_term];
        $types = "is";
    
        if ($category_id !== null) {
            $query .= " AND n.category_id " . ($category_id === 0 ? "IS NULL" : "= ?");
            if ($category_id !== 0) {
                $params[] = $category_id;
                $types .= "i";
            }
        }
    
        $query .= " ORDER BY n.created_at DESC";

        $result = $this->db->q($query, $types, ...$params);
    
        if ($result) {
            foreach ($result as &$note) {
                $note['content'] = $this->generatePreview($note['content'], $content_preview_length);
            }
        }
    
        return $result ?: [];
    }

    public function getNoteImages($note_id, $user_id) {
        $query = "SELECT si.image_path 
                  FROM shared_item_images si
                  JOIN sticky_notes sn ON si.item_id = sn.id
                  WHERE si.item_id = ? AND si.item_type = 'stickynote' AND sn.user_id = ?";
        return $this->db->q($query, "ii", $note_id, $user_id);
    }

    private function deleteNoteImages($note_id) {
        $query = "DELETE FROM shared_item_images WHERE item_id = ? AND item_type = 'stickynote'";
        $this->db->q($query, "i", $note_id);
    }
}

?>