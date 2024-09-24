<?php
use Dashboard\Stickynote\StickyNoteControllerFactory;

$controller = StickyNoteControllerFactory::create($db, $user);
$categories = $controller->getCategories();

$GET_category_id = (isset($_GET['category_id']) ? $_GET['category_id'] : '');

$note = null;
$action = $_GET['action'] ?? 'create';
$note_id = $_GET['note_id'] ?? 0;

if ($action == 'edit' && $note_id) {
    $note = $controller->getNoteData($note_id);
}

$post_url = $action == 'edit' ? "/stickynotes/note/edit/{$note_id}" : "/stickynotes/note/create/0";
$submit_button_text = $action == 'edit' ? 'Update Note' : 'Create Note';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action == 'create') {
        $result = $controller->handleCreateNote();
        if ($result['success']) {
            triggerResponse([
                "triggerNotelist" => true, 
                "triggerNoteCatlist" => true,
                "closeSpecificModalEvent" => ["dialog-note"],
                "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message'] ?? 'Note created successfully']
            ]);
        } else {
            triggerResponse([
                "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message'] ?? 'Failed to create note']
            ]);
        }
    } 
    elseif ($action == 'edit') {
        $result = $controller->handleEditNote();
        
        if ($result['success']) {
            triggerResponse([
                "refreshModal" => true,
                "triggerNotelist" => true, 
                "triggerNoteCatlist" => true,
                "globalMessagePopupUpdate" => ['type' => 'success', 'message' => $result['message'] ?? 'Note updated successfully']
            ]);
        } else {
            triggerResponse([
                "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message'] ?? 'Failed to update note']
            ]);
        }
    }
}

// Handle delete request
// The handleDeleteNote method expects note_id in $_POST
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $action == 'edit' && $note_id) {
    $_POST['note_id'] = $note_id; 

    $result = $controller->handleDeleteNote();
    
    if ($result['success']) {
        triggerResponse([
            "triggerNotelist" => true, 
            "triggerNoteCatlist" => true,
            "closeSpecificModalEvent" => ["dialog-note"], 
            "globalMessagePopupUpdate" => ['type' => 'success', 'message' => 'Note deleted successfully']
        ]);
    } else {
        triggerResponse([
            "globalMessagePopupUpdate" => ['type' => 'error', 'message' => $result['message'] ?? 'Failed to delete note']
        ]);
    }
}

?>

<div id="dialog-note" 
    class="modal-container" 
    hx-get="/stickynotes/note/edit/<?=htmlspecialchars($note_id); ?>" 
    hx-trigger="refreshModal from:body" 
    hx-target="#dialog-note" 
    hx-swap="outerHTML"
    >
    <div class="dialog" style="width: 70rem; height: 90%;">
        <div class="dialog-header">
            <span>Sticky note</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
        <!-- end of modal header -->

            <div class="flex-table nice-form-group" style="align-items: stretch; height: 100%;">
                <form id="form_stickynote" method="POST" hx-post="<?=$post_url?>" hx-target="#dialog-note .formOuter" hx-swap="beforeend" style="height: 100%; height: 100%; align-items: stretch; display: flex; flex-flow: column;">
                <div class="flex-row">
                    <div class="flex-cell">
                        <input type="text" placeholder="Note title" name="title" id="note_title" value="<?=htmlspecialchars($note['title'] ?? '');?>">
                    </div>
                    <div class="flex-cell flex-cell-shrink">
                        <select name="category_id" id="note_category">
                            <?php 
                            foreach ($categories as $category): ?>
                                <option value="<?=htmlspecialchars($category['id'] ?? '0'); ?>"<?php
                                    if ($action == 'edit') {
                                        echo ($note['category_id'] ?? '') == $category['id'] ? 'selected' : '';
                                    } else {
                                        echo ($category['id'] ?? '') == $GET_category_id ? 'selected' : '';
                                    }
                                    ?>>
                                    <?=htmlspecialchars($category['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-cell flex-cell-shrink flex-cell-vcenter" style="position: relative;">
                        <button class="btn btn-purple" 
                                id="jaja"
                                hx-get="/stickynotes/audio-transcribe"
                                hx-target="#audio-transcribe-box" 
                                hx-swap="innerHTML"
                                tabindex="-1"
                                data-type="small-popup"
                                data-popup-wrapper="audio-transcribe-box"
                                <?=(strlen($user->getChatGPTAPIKey()) < 2 ? 'title="You need to input a openAI GPT Key in account settings" disabled':'')?>>
                                <i class="fa fa-lg (20% increase) fa-microphone"></i>
                        </button>
                        <div class="small-popup-box-wrapper">
                            <div id="audio-transcribe-box"><!-- content fetches here --></div>
                        </div>
                    </div>
                    <div class="flex-cell flex-cell-shrink flex-cell-vcenter">
                        <button type="submit" form="form_stickynote" class="btn btn-blue">Upload file</button>
                    </div>
                </div>
                <div class="flex-row">
                    <div class="flex-cell">
                        Filer här kanske
                    </div>
                </div>
                <div class="flex-row" style="flex: 1;">
                    <div class="flex-cell">
                        <textarea name="content" id="note_content" class="tinymce_editor" style="height: 300px;" placeholder="Note content"><?=htmlspecialchars($note['content'] ?? '');?></textarea>
                        <style>
                            .tox-tinymce { height: 100% !important; }
                        </style>
                    </div>
                </div>
                <div class="flex-row">
                    <div class="flex-cell flex-vertical-center flex-right">
                    <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="note_id" value="<?=htmlspecialchars($note_id); ?>">
                    <?php endif; ?>
                    </div>
                    <div class="flex-cell flex-cell-shrink flex-cell-vcenter">
                        <label>
                            <input type="checkbox" name="is_pinned" id="note_is_pinned">
                            Pin this note
                        </label>
                    </div>
                </div>
                </form>

            
                <div class="flex-row">
                    <div class="flex-cell">
                        <?php if ($action == 'edit'): ?>
                            <form id="form_deleteNote" hx-delete="<?=(isset($post_url) ? $post_url : '')?>" hx-target="body" hx-swap="beforeend">
                                <button type="submit" class="btn btn-light-gray btn-hover-red" tabindex="-1">Delete note</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="flex-cell flex-vertical-center flex-right">
                        <button type="submit" form="form_stickynote" class="btn btn-green"><?=htmlspecialchars($submit_button_text);?></button>
                    </div>
                </div>
            </div>

        <!-- end of modal -->
        </div>
    </div>
</div>
