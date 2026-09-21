<?php
use Dashboard\Core\Sanitize;

use Dashboard\Core\HtmxEvents;

$controller = new \Dashboard\Stickynote\StickyNoteController($db, $user);
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
            triggerResponse(HtmxEvents::successResponse(
                $result['message'] ?? 'Note created successfully',
                [
                    HtmxEvents::TRIGGER_NOTELIST => true,
                    HtmxEvents::REFRESH_NOTE_CATEGORY_LIST => true,
                ] + HtmxEvents::closeSpecificModal('dialog-note')
            ));
        } else {
            triggerResponse(HtmxEvents::errorResponse($result['message'] ?? 'Failed to create note'));
        }
    } 
    elseif ($action == 'edit') {
        $result = $controller->handleEditNote();
        
        if ($result['success']) {
            triggerResponse(HtmxEvents::successResponse(
                $result['message'] ?? 'Note updated successfully',
                [
                    HtmxEvents::REFRESH_MODAL => true,
                    HtmxEvents::TRIGGER_NOTELIST => true,
                    HtmxEvents::REFRESH_NOTE_CATEGORY_LIST => true,
                ]
            ));
        } else {
            triggerResponse(HtmxEvents::errorResponse($result['message'] ?? 'Failed to update note'));
        }
    }
}

// Handle delete request
// The handleDeleteNote method expects note_id in $_POST
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $action == 'edit' && $note_id) {
    $_POST['note_id'] = $note_id; 

    $result = $controller->handleDeleteNote();
    
    if ($result['success']) {
        triggerResponse(HtmxEvents::successResponse(
            'Note deleted successfully',
            [
                HtmxEvents::TRIGGER_NOTELIST => true,
                HtmxEvents::REFRESH_NOTE_CATEGORY_LIST => true,
            ] + HtmxEvents::closeSpecificModal('dialog-note')
        ));
    } else {
        triggerResponse(HtmxEvents::errorResponse($result['message'] ?? 'Failed to delete note'));
    }
}

?>

<div id="dialog-note" 
    class="modal-container" 
    hx-get="/stickynotes/note/edit/<?=Sanitize::e($note_id); ?>" 
    hx-trigger="refreshModal from:body" 
    hx-target="#dialog-note" 
    hx-swap="outerHTML"
    >
    <div class="dialog dialog-lg" style="height: 90%;">
        <div class="dialog-header">
            <span>Sticky note</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
        <!-- end of modal header -->

            <div class="flex-table nice-form-group" style="align-items: stretch; height: 100%;">
                <form id="form_stickynote" method="POST" hx-post="<?=$post_url?>" hx-target="#dialog-note .formOuter" hx-swap="beforeend" style="height: 100%; height: 100%; align-items: stretch; display: flex; flex-flow: column;">
                    <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
                <div class="flex-row">
                    <div class="flex-cell">
                        <input type="text" placeholder="Note title" name="title" id="note_title" value="<?=Sanitize::e($note['title'] ?? '');?>">
                    </div>
                    <div class="flex-cell flex-cell-shrink">
                        <select name="category_id" id="note_category">
                            <?php 
                            foreach ($categories as $category): ?>
                                <option value="<?=Sanitize::e($category['id'] ?? '0'); ?>"<?php
                                    if ($action == 'edit') {
                                        echo ($note['category_id'] ?? '') == $category['id'] ? 'selected' : '';
                                    } else {
                                        echo ($category['id'] ?? '') == $GET_category_id ? 'selected' : '';
                                    }
                                    ?>>
                                    <?=Sanitize::e($category['title']); ?>
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
                                <span class="mic-icon" aria-hidden="true">🎤</span>
                        </button>
                        <div class="small-popup-box-wrapper">
                            <div id="audio-transcribe-box"><!-- content fetches here --></div>
                        </div>
                    </div>
                    <div class="flex-cell flex-cell-shrink flex-cell-vcenter">
                        <button type="button"
                                class="btn btn-blue btn-with-icon"
                                tabindex="-1"
                                data-switch-tab="attachments"
                                <?= ($action == 'edit' && $note_id) ? '' : 'disabled' ?>>
                            <?= svgIcon('attachments') ?>Attachments
                        </button>
                    </div>
                </div>
                <div class="flex-row">
                    <div class="flex-cell">
                       
                    </div>
                </div>
                <?php
                // ---- Tab system: Content / Attachments ----
                // Same pattern as the task modal. Attachments tab appears only
                // in edit mode (a note must exist before files attach).
                $noteAttachmentCount = ($action == 'edit' && $note_id)
                    ? (new \Dashboard\Core\AttachmentService($db))->countForItem((int)$user->getUserId(), (int)$note_id, 'stickynote')
                    : 0;
                $noteHasAttachments = $noteAttachmentCount > 0;
                ?>
                <?php
                // Tab order is drag-reorderable and stored per user (UiPreferenceService).
                $tabOrder = (new \Dashboard\Core\UiPreferenceService($db))
                    ->getTabOrder((int)$user->getUserId(), 'note', ['content', 'attachments']);
                // Open on the first visible tab of the saved order.
                $activeTab = \Dashboard\Core\UiPreferenceService::defaultTab($tabOrder, [
                    'content' => true,
                    'attachments' => $noteHasAttachments,
                ]);
                $tabButtons = [];

                ob_start(); ?>
                    <button type="button" class="modal-tab<?= $activeTab === 'content' ? ' active' : '' ?>" data-tab="content"><?= svgIcon('description', ['class' => 'modal-tab-icon']) ?>Content</button>
                <?php $tabButtons['content'] = ob_get_clean();

                ob_start(); ?>
                    <button type="button" class="modal-tab<?= $activeTab === 'attachments' ? ' active' : '' ?>" data-tab="attachments" data-tab-conditional data-tab-hide-when-empty <?= $noteHasAttachments ? '' : 'hidden' ?>><?= svgIcon('attachments', ['class' => 'modal-tab-icon']) ?>Attachments<span class="modal-tab-badge" data-tab-badge="attachments" <?= $noteHasAttachments ? '' : 'hidden' ?>><?= $noteAttachmentCount ?></span></button>
                <?php $tabButtons['attachments'] = ob_get_clean();
                ?>
                <div class="flex-row">
                    <div class="flex-cell">
                        <div class="modal-tabs note-modal-tabs" data-modal-tabs data-tab-order-context="note">
                            <?php foreach ($tabOrder as $tabName): ?>
                                <?= $tabButtons[$tabName] ?? '' ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-tab-pane modal-tab-pane-flex<?= $activeTab === 'content' ? ' active' : '' ?>" data-tab-pane="content">
                    <div class="flex-row" style="flex: 1;">
                        <div class="flex-cell">
                            <textarea name="content" id="note_content" class="tinymce_editor" style="height: 300px;" placeholder="Note content"><?=Sanitize::e($note['content'] ?? '');?></textarea>
                            <style>
                                .tox-tinymce { height: 100% !important; }
                            </style>
                        </div>
                    </div>
                </div>

                <div class="modal-tab-pane<?= $activeTab === 'attachments' ? ' active' : '' ?>" data-tab-pane="attachments">
                    <?php if ($action == 'edit' && $note_id): ?>
                        <?php
                        // Attachments section (edit mode only — a note must exist
                        // before files can be attached to it).
                        $attachmentItemType = 'stickynote';
                        $attachmentItemId = (int)$note_id;
                        $attachmentCount = $noteAttachmentCount;
                        include BASE_DIR . '/views/core/partial/attachments.php';
                        ?>
                    <?php else: ?>
                        <div class="attachment-list-empty">Save the note first, then you can attach files.</div>
                    <?php endif; ?>
                </div>
                <div class="flex-row">
                    <div class="flex-cell flex-vertical-center flex-right">
                    <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="note_id" value="<?=Sanitize::e($note_id); ?>">
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
                                <button type="submit" class="btn btn-light-gray btn-hover-red btn-with-icon" tabindex="-1"><?= svgIcon('delete') ?>Delete note</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="flex-cell flex-vertical-center flex-right">
                        <button type="submit" form="form_stickynote" data-form-submit class="btn btn-green btn-with-icon"><?= svgIcon('save') ?><?=Sanitize::e($submit_button_text);?></button>
                    </div>
                </div>
            </div>

        <!-- end of modal -->
        </div>
    </div>
</div>
