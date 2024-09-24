<?php
// categories.php
use Dashboard\Stickynote\StickyNoteControllerFactory;

$controller = StickyNoteControllerFactory::create($db, $user);
// Main page content
$allCategories = $controller->getCategories();


$action = $_GET['action'] ?? 'create';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $result = $controller->handleCreateCategory();
        if ($result['success']) {
            triggerResponse([
                "refreshThisModal" => true,
                "triggerNoteCatlist" => true,
                "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message'] ?? 'Category created successfully']
            ]);
        } else {
            triggerResponse([
                "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message'] ?? 'Failed to create category']
            ]);
        }
    } 
    elseif ($action === 'edit') {
        $result = $controller->handleEditCategory();
        if ($result['success']) {
            triggerResponse([
                "refreshThisModal" => true,
                "triggerNoteCatlist" => true,
                "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message'] ?? 'Category updated successfully']
            ]);
        } else {
            triggerResponse([
                "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message'] ?? 'Failed to update category']
            ]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $result = $controller->handleDeleteCategory();
    if ($result['success']) {
        triggerResponse([
            "refreshThisModal" => true,
            "triggerNoteCatlist" => true,
            "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message'] ?? 'Category deleted successfully']
        ]);
    } else {
        triggerResponse([
            "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message'] ?? 'Failed to delete category']
        ]);
    }
}

// Handle cancel action
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    // Return empty content to clear the edit form
    triggerResponse([
        "refreshThisModal" => true
    ]);
    exit;
}

// Check if we're handling an AJAX request for the edit form
if (isset($_GET['action']) && $_GET['action'] === 'get-edit-form') {
    $categoryId = $_GET['id'] ?? null;
    $categoryData = $categoryId ? $controller->getCategoryData($categoryId) : null;

    // Extract the first (and presumably only) category from the result
    $category = $categoryData[0] ?? null;
    
    if ($category) {
        // Return only the form HTML for HTMX to inject
?>
            <form hx-post="/stickynotes/category/dialog/?action=edit" hx-target="#dialog-category-settings .formOuter" hx-swap="outerHTML">
            <input type="hidden" name="category_id" value="<?= htmlspecialchars($category['id'] ?? '') ?>">
            <div class="flex-table" style="background: #e2edf3; border-radius: 0.5rem; margin-bottom: 2rem;">
                <div class="flex-row">
                    <div class="flex-cell">
                        <h3 style="margin:0;">Edit category:</h3>
                    </div>
                </div>
                <div class="flex-row">
                    <div class="flex-cell">
                        <input type="text" name="title" value="<?= htmlspecialchars($category['title'] ?? '') ?>" required>
                    </div>
                    <div class="flex-cell flex-cell-shrink">
                        <input type="color" name="color" value="<?= htmlspecialchars($category['color'] ?? '') ?>">
                    </div>
                </div>
            </form>
                <div class="flex-row">
                    <div class="flex-cell flex-cell-shrink">
                        <form id="form_deleteNote" hx-delete="/stickynotes/category/dialog/?category_id=<?= htmlspecialchars($category['id'] ?? '') ?>" hx-target="body" hx-swap="beforeend">
                            <button type="submit" class="btn btn-light-gray btn-hover-red" tabindex="-1" hx-confirm="Are you sure you want to delete this category?">Delete category</button>
                        </form>
                    </div>
                    <div class="flex-cell">
                        <button type="button" class="btn btn-light-gray" hx-get="/stickynotes/category/dialog/?action=cancel" hx-target="#dialog-category-settings .formOuter" hx-swap="innerHTML">Cancel</button>
                    </div>
                    <div class="flex-cell flex-right">
                        <button type="submit" class="btn btn-green">Update</button>
                    </div>
                </div>
            </div>
        
<?php
        exit; // Stop further execution
    } else {
        echo "You cannot edit this category.<br><br>";
        exit;
    }
}


?>
<div 
    id="dialog-category-settings" 
    class="modal-container"
    hx-get="/stickynotes/category/dialog/"
    hx-trigger="refreshThisModal from:body"
    hx-target="#dialog-category-settings"
    hx-swap="outerHTML"
>
    <div class="dialog" style="width: 60rem;">
        <div class="dialog-header">
            <span>Edit categories</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter nice-form-group">
            
            <div class="flex-table">
                <div class="flex-row">
                    <div class="flex-cell sn-category-list" style="padding-right:2rem;">
                    <br>
                        <ul id="category-list">
                        <?php 
                            foreach ($allCategories as $category): 
                                $isUncategorized = $category['id'] === null;
                                $categoryId = $isUncategorized ? '0' : htmlspecialchars($category['id']);

                                if($isUncategorized == 0) {
                                    echo '
                                        <li id="category-item-'.$categoryId.'">
                                            <a href="#" 
                                            hx-get="/stickynotes/category/dialog/?action=get-edit-form&id='.$categoryId.'"
                                            hx-target="#edit-category-form" 
                                            hx-swap="innerHTML"
                                            hx-trigger="click">
                                                <span class="sn-category-color" style="background-color: '.htmlspecialchars($category['color']).';"></span>
                                                '.htmlspecialchars($category['title']).'
                                                <span class="sn-category-count">'.intval($category['note_count']).'</span>
                                            </a>
                                        </li>
                                    ';
                                }
                            endforeach; 
                        ?>
                        </ul>
                    </div>
                    <div class="flex-cell">

                        <div id="edit-category-form">
                            <!-- This div will be populated with the edit form when 'Edit' is clicked -->
                        </div>

                        <form id="add-category-form" hx-post="/stickynotes/category/dialog/?action=create" hx-target="#dialog-category-settings .formOuter" hx-swap="beforeend">
                            <div class="flex-table">
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <h3 style="margin:0;">Add a new category:</h3>
                                    </div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <input type="text" name="title" placeholder="New category name" required>
                                    </div>
                                    <div class="flex-cell flex-cell-shrink">
                                        <input type="color" name="color" value="#b2e4e5">
                                    </div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell"></div>
                                    <div class="flex-cell"></div>
                                    <div class="flex-cell"><button type="submit" class="btn btn-green">Add Category</button></div>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>