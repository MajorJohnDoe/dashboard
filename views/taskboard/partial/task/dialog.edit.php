<?php
use Dashboard\Core\Sanitize;
use Dashboard\Core\HtmxEvents;
use Dashboard\Core\AttachmentService;
use Dashboard\Taskboard\TaskController;
use Dashboard\Taskboard\ColumnController;

// User wants to add a new task to column
if($_GET['action'] == 'new' && isset($_GET['column_id'])) {
    $post_url = '/task/dialog/'.$_GET['action'].'/'.$_GET['column_id'];
}

    // User wants to edit a task
    if($_GET['action'] == 'edit' && isset($_GET['column_id']) && isset($_GET['task_id'])) {
        $post_url = '/task/dialog/'.$_GET['action'].'/'.$_GET['column_id'].'/'.$_GET['task_id'];

        $task = new TaskController($db, $user);
        $result = $task->handleGetTaskDetails($_GET['task_id']);
        if ($result['success']) {
            $taskData = $result['task'];
            $taskTitle = $taskData['title'];
            $taskDescription = $taskData['description'];
            $taskChecklist = $taskData['checklist'];
            $taskPriority = $taskData['priority'];
            $taskSelectLabels = $taskData['labels'];
            $taskIsResolved = $taskData['resolved_date'];
            $taskScheduleId = (int)($taskData['schedule_id'] ?? 0);
        }

        // Attachment count for the tab badge (0 hides the Attachments tab)
        $attachmentService = new AttachmentService($db);
        $taskAttachmentCount = $attachmentService->countForItem((int)$user->getUserId(), (int)$_GET['task_id'], 'task');
    }


    // #MARK: NEW task - form post
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'new' && isset($_POST['column_id'])) {
        $task = new TaskController($db, $user);
        $result = $task->handleCreateTask($_POST);
    
        if ($result['success'] == true) {
            triggerResponse(HtmxEvents::successResponse(
                $result['message'],
                [
                    HtmxEvents::TASK_BOARD_COLUMN_LIST => true,
                    HtmxEvents::CLOSE_MODAL => ['modalId' => 'dialog-column-add-task'],
                ]
            ));
        } else {
            triggerResponse(HtmxEvents::errorResponse($result['message']));
        }
    }
    

    // #MARK: DELETE task - form post
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $_GET['action'] == 'edit' && isset($_GET['task_id'])) {
        $taskId = $_GET['task_id'];

        $task = new TaskController($db, $user);
        $result = $task->handleDeleteTask($taskId);

        if ($result['success'] == true) {
            triggerResponse(HtmxEvents::successResponse(
                $result['message'],
                [
                    HtmxEvents::TASK_BOARD_COLUMN_LIST => true,
                    HtmxEvents::CLOSE_MODAL => true,
                    HtmxEvents::CLOSE_SPECIFIC_MODAL => ['dialog-task-delete-confirm', 'dialog-column-add-task'],
                    HtmxEvents::REFRESH_TASK_HISTORY => true,
                ]
            ));
        } else {
            triggerResponse(HtmxEvents::errorResponse($result['message']));
        }
    }


    // #MARK: EDIT task - form post
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_GET['action'] == 'edit' && isset($_POST['column_id']) && isset($_POST['task_id'])) {
        $task = new TaskController($db, $user);
        $result = $task->handleUpdateTask($_POST);

        if ($result['success'] == true) {
            // Controller decides: task moved to another column -> close modal,
            // otherwise refresh the modal in place.
            $closeEvent = !empty($result['close_modal']) ? HtmxEvents::CLOSE_MODAL : HtmxEvents::REFRESH_MODAL;

            triggerResponse(HtmxEvents::successResponse(
                $result['message'],
                [
                    HtmxEvents::TASK_BOARD_COLUMN_LIST => true,
                    HtmxEvents::REFRESH_TASK_HISTORY => true,
                    $closeEvent => true,
                ]
            ));
        } else {
            triggerResponse(HtmxEvents::errorResponse($result['message']));
        }
    }
?>
<div id="dialog-column-add-task" 
     class="modal-container"
     hx-get="/task/dialog/edit/<?=$_GET['column_id']?><?=(isset($_GET['task_id']) ? '/'.$_GET['task_id'] : '')?>"
     hx-trigger="refreshModal from:body"
     hx-target="#dialog-column-add-task"
     hx-swap="outerHTML"
     >

    <div class="dialog dialog-lg">
        <div class="dialog-header">
            <span>Task</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
            <!-- end of modal header -->
            <form id="form_addTask" method="POST" hx-post="<?=($post_url ?? '')?>" hx-target="#dialog-column-add-task .formOuter" hx-swap="beforeend">
                <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
                <?php
                    // Message to user that task has a resolved date from moving task to a column that has a resolve flag.
                    // Resolve flag on a column sets task task_resolved_date, kinda like a archived task
                    if(isset($taskIsResolved) && $taskIsResolved != null) { 
                        $taskColumn = new ColumnController($db, $user);
                        $taskColumns = $taskColumn->getColumnsForBoard($user->getActiveTaskBoard());

                            echo '
                            <div class="flex-table nice-form-group task-resolved-banner">
                                <div class="flex-row">
                                    <div class="flex-cell flex-vertical-center flex-cell-shrink">
                                        <label for="task_title">Move task back to a column:</label>
                                    </div>
                                    <div class="flex-cell flex-vertical-center flex-cell-shrink">
                                        <select name="move_task_column_id" id="move_task_column_id">
                                            <option value="0">pick a column</option>';

                                        if($taskColumns != false) {
                                            foreach ($taskColumns['columns'] as $column) {
                                                echo '<option value="'.$column['id'].'">'.Sanitize::e(html_entity_decode($column['column_name'], ENT_QUOTES, 'UTF-8')).'</option>';
                                            }
                                        }
                    
                            echo '      </select>
                                    </div>
                                    <div class="flex-cell flex-vertical-center flex-cell-shrink">
                                        <input type="hidden" name="task_id" value="'.(isset($_GET['task_id']) ? Sanitize::e($_GET['task_id']) : null).'">
                                        <input type="submit" value="Move task" form="form_addTask" class="btn btn-green">
                                    </div>
                                </div>
                            </div>
                            ';
                    }
                ?>
                <div class="nice-form-group">
                    <div class="edit-grid">
                        <!-- Left Column -->
                        <div class="left-column">
                            <div class="flex-table">
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <label for="task_title">Task title:</label>
                                        <input type="text" name="task_title" id="task_title" autocomplete="off" autofocus value="<?=(isset($taskTitle) ? Sanitize::e($taskTitle) : '')?>">
                                    </div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell flex-cell-shrink flex-vertical-center" style="position: relative;">
                                        <div class="label-search-field">
                                            <?= svgIcon('tag', ['class' => 'label-search-icon']) ?>
                                            <input 
                                                class="label-search-input"
                                                autocomplete="off"
                                                type="search" 
                                                name="search-label" 
                                                id="search-label" 
                                                placeholder="Search for label" 
                                                hx-post="/task/label/search"
                                                hx-trigger="input changed delay:100ms, focus, search-label"
                                                hx-target="#search-label-result" 
                                                hx-swap="innerHTML"
                                                data-type="small-popup"
                                                data-popup-wrapper="search-label-result">
                                            <div class="small-popup-box-wrapper">
                                                <div id="search-label-result"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    // Preselected labels for the current task. Whitespace between
                                    // chips is harmless — #selectedLabelsContainer is a flex
                                    // container, so spacing is controlled by `gap` in task.css.
                                    $selectedLabelDetails = isset($taskSelectLabels) && is_array($taskSelectLabels) ? $taskSelectLabels : [];
                                    ?>
                                    <div class="flex-cell flex-cell-vcenter" id="selectedLabelsContainer">
                                        <?php foreach ($selectedLabelDetails as $label): ?>
                                            <input type="hidden" id="hiddenLabelId_<?= Sanitize::e($label['label_id']) ?>" name="selectedLabels[]" value="<?= Sanitize::e($label['label_id']) ?>">
                                            <span id="visualLabelId_<?= Sanitize::e($label['label_id']) ?>" style="background-color: <?= Sanitize::e($label['label_color']) ?>;"><?= Sanitize::e($label['label_name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php
                                // ---- Tab system: Description / Checklist / Attachments ----
                                // Description is always visible. Checklist and Attachments
                                // tabs appear only when content exists or the user initiates
                                // adding one (sidebar buttons switch to the tab).
                                // The task_desc textarea lives inside the Description pane
                                // below (single instance — no duplicate id).
                                $taskIdForTabs = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
                                $hasChecklist = isset($taskChecklist) && $taskChecklist != null;
                                // Badge counts: the checklist is stored as JSON, the
                                // attachments as a count (+ staged pending files).
                                $checklistItemCount = $hasChecklist ? count(json_decode($taskChecklist, true) ?: []) : 0;
                                // New-task mode: show the Attachments tab when there are
                                // staged pending files (modal re-opened mid-flow). Loaded
                                // once here and reused for the hidden token fields below.
                                $pendingAttachments = ($taskIdForTabs === 0)
                                    ? (new AttachmentService($db))->getPending((int)$user->getUserId(), 'task')
                                    : [];
                                $pendingCount = count($pendingAttachments);
                                $attachmentTabCount = (int)($taskAttachmentCount ?? 0) + $pendingCount;
                                $hasAttachments = $attachmentTabCount > 0;
                                ?>
                                <?php
                                // Tab order is drag-reorderable and stored per user
                                // (UiPreferenceService → user_ui_preference). Each button
                                // is buffered so its markup stays as-is while the order
                                // comes from the saved preference.
                                $tabOrder = (new \Dashboard\Core\UiPreferenceService($db))
                                    ->getTabOrder((int)$user->getUserId(), 'task', ['description', 'checklist', 'attachments']);
                                // The dialog opens on the first tab of the saved order that
                                // is actually visible (conditional tabs can be hidden while
                                // empty), so reordering also picks the opening tab.
                                $activeTab = \Dashboard\Core\UiPreferenceService::defaultTab($tabOrder, [
                                    'description' => true,
                                    'checklist' => $hasChecklist,
                                    'attachments' => $hasAttachments,
                                ]);
                                $tabButtons = [];

                                ob_start(); ?>
                                    <button type="button" class="modal-tab<?= $activeTab === 'description' ? ' active' : '' ?>" data-tab="description">
                                        <?= svgIcon('description', ['class' => 'modal-tab-icon']) ?>Description
                                    </button>
                                <?php $tabButtons['description'] = ob_get_clean();

                                ob_start(); ?>
                                    <button type="button" class="modal-tab<?= $activeTab === 'checklist' ? ' active' : '' ?>" data-tab="checklist" data-tab-conditional data-tab-hide-when-empty <?= $hasChecklist ? '' : 'hidden' ?>>
                                        <?= svgIcon('check', ['class' => 'modal-tab-icon']) ?>Checklist<span class="modal-tab-badge" data-tab-badge="checklist" <?= $hasChecklist ? '' : 'hidden' ?>><?= $checklistItemCount ?></span>
                                    </button>
                                <?php $tabButtons['checklist'] = ob_get_clean();

                                ob_start(); ?>
                                    <button type="button" class="modal-tab<?= $activeTab === 'attachments' ? ' active' : '' ?>" data-tab="attachments" data-tab-conditional data-tab-hide-when-empty <?= $hasAttachments ? '' : 'hidden' ?>>
                                        <?= svgIcon('attachments', ['class' => 'modal-tab-icon']) ?>Attachments<span class="modal-tab-badge" data-tab-badge="attachments" <?= $hasAttachments ? '' : 'hidden' ?>><?= $attachmentTabCount ?></span>
                                    </button>
                                <?php $tabButtons['attachments'] = ob_get_clean();
                                ?>
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <div class="modal-tabs task-modal-tabs" data-modal-tabs data-tab-order-context="task">
                                            <?php foreach ($tabOrder as $tabName): ?>
                                                <?= $tabButtons[$tabName] ?? '' ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-tab-pane modal-tab-pane-flex<?= $activeTab === 'description' ? ' active' : '' ?>" data-tab-pane="description">
                                    <div class="flex-row">
                                        <div class="flex-cell">
                                            <textarea name="task_desc" id="task_desc" class="tinymce_editor tinymce-hidden" aria-hidden="true"><?=(isset($taskDescription) ? Sanitize::e($taskDescription) : '')?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-tab-pane<?= $activeTab === 'checklist' ? ' active' : '' ?>" data-tab-pane="checklist">
                                    <div class="flex-row task_checklist"></div>
                                    <?php
                                    if ($hasChecklist) {
                                        echo '
                                        <div
                                            hx-get="/task/checklist/edit/'.($taskIdForTabs ?: '').'" 
                                            hx-trigger="load, taskChecklist from:body" 
                                            hx-target="this" hx-swap="innerHTML">
                                        </div>';
                                    }
                                    ?>
                                </div>

                                <div class="modal-tab-pane<?= $activeTab === 'attachments' ? ' active' : '' ?>" data-tab-pane="attachments">
                                    <?php
                                    // Attachments work in BOTH modes:
                                    //  - edit mode (task exists): files persist immediately
                                    //  - new mode (no task yet): files are staged as session
                                    //    pending uploads; token hidden fields are posted with
                                    //    the form and claimed into the DB on task creation.
                                    //    Abandoned staged files are swept after 24 h.
                                    $attachmentItemType = 'task';
                                    $attachmentItemId = $taskIdForTabs; // 0 = new task → pending staging
                                    $attachmentCount = $taskAttachmentCount ?? 0;
                                    include BASE_DIR . '/views/core/partial/attachments.php';

                                    if ($taskIdForTabs === 0) {
                                        // Pre-existing pending files (e.g. modal re-opened mid-flow)
                                        foreach (array_keys($pendingAttachments) as $pendingToken) {
                                            echo '<input type="hidden" name="pending_attachments[]" value="' . Sanitize::e($pendingToken) . '">';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="right-column">
                            <div class="flex-table">
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <span class="form-label">Add to task</span>
                                        <!-- Labels Button -->
                                        <button class="btn btn-dark-gray btn-block btn-with-icon" 
                                                hx-get="/label/edit"
                                                hx-target="body" 
                                                hx-swap="beforeend"
                                                tabindex="-1">
                                            <?= svgIcon('tag') ?>Edit labels
                                        </button>
                                        <!-- Checklist Button: creates the checklist when the task
                                             has none, otherwise it only reveals the existing tab
                                             (no fetch — the pane already holds the editor, so a
                                             fetch would duplicate #checklist-items). Disabled while
                                             the checklist has items: the tab is always visible then. -->
                                        <button type="button" 
                                                class="btn btn-dark-gray btn-block btn-with-icon" 
                                                tabindex="-1"
                                                data-switch-tab="checklist"
                                                <?php if (!$hasChecklist): ?>
                                                hx-get="/task/checklist/new/0"
                                                hx-target="#dialog-column-add-task .task_checklist" 
                                                hx-swap="innerHTML" 
                                                <?php endif; ?>
                                                <?= ($hasChecklist && $checklistItemCount > 0) ? 'disabled' : '' ?>>
                                            <?= svgIcon('check') ?>Checklist
                                        </button>
                                        <!-- Attachments Button: switches to the Attachments tab.
                                             Works in new-task mode too — files are staged as
                                             pending uploads and claimed when the task is saved. -->
                                        <button type="button"
                                                class="btn btn-dark-gray btn-block btn-with-icon"
                                                tabindex="-1"
                                                data-switch-tab="attachments">
                                            <?= svgIcon('attachments') ?>Attachments
                                        </button>
                                    </div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <span class="form-label">Priority</span>
                                        <div class="pill-select">
                                            <!-- Lowest Priority -->
                                            <input type="radio" id="priority-lowest" name="task_priority" value="0"
                                                <?= !isset($taskPriority) || $taskPriority == "0" ? 'checked' : '' ?>>
                                            <label for="priority-lowest" class="pill-select-label priority-lowest">Lowest</label>

                                            <!-- Low Priority -->
                                            <input type="radio" id="priority-low" name="task_priority" value="1"
                                                <?= isset($taskPriority) && $taskPriority == "1" ? 'checked' : '' ?>>
                                            <label for="priority-low" class="pill-select-label priority-low">Low</label>

                                            <!-- Alarming Priority -->
                                            <input type="radio" id="priority-alarming" name="task_priority" value="2"
                                                <?= isset($taskPriority) && $taskPriority == "2" ? 'checked' : '' ?>>
                                            <label for="priority-alarming" class="pill-select-label priority-alarming">Alarming</label>

                                            <!-- Critical Priority -->
                                            <input type="radio" id="priority-critical" name="task_priority" value="3"
                                                <?= isset($taskPriority) && $taskPriority == "3" ? 'checked' : '' ?>>
                                            <label for="priority-critical" class="pill-select-label priority-critical">Critical</label>

                                            <!-- Highest Priority -->
                                            <input type="radio" id="priority-highest" name="task_priority" value="4"
                                                <?= isset($taskPriority) && $taskPriority == "4" ? 'checked' : '' ?>>
                                            <label for="priority-highest" class="pill-select-label priority-highest">Highest</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <span class="form-label">Actions</span><br>
                                        <!-- Duplicate task Button -->
                                        <div style="position: relative;">
                                            <button class="btn btn-dark-gray btn-block btn-with-icon" 
                                                    id="duplicate-task-btn"
                                                    hx-get="/task/duplicate/dupe/<?=(isset($_GET['task_id']) ? $_GET['task_id'] : '')?>"
                                                    hx-target="#duplicate-task-box" 
                                                    hx-swap="innerHTML"
                                                    tabindex="-1"
                                                    data-type="small-popup"
                                                    data-popup-wrapper="duplicate-task-box"
                                                    <?=(isset($_GET['action']) && $_GET['action'] == 'new'? 'disabled':'')?>>
                                                    <?= svgIcon('duplicate') ?>Duplicate
                                                </button>
                                            <div class="small-popup-box-wrapper">
                                                <div id="duplicate-task-box"><!-- content goes here --></div>
                                            </div>
                                        </div>

                                        <!-- Recurring schedule Button: opens the slide-out
                                             recurrence panel (task edit mode only). Uses hx-swap="none"
                                             + a JS fetch (TaskContextMenu.openRecurrencePanel) instead of
                                             a plain hx-get into body — htmx swaps leave the response's
                                             <script> tags in the DOM as inert elements, which pile up on
                                             repeated opens. NOT .open-modal-btn, which ModalManager
                                             ignores inside forms. -->
                                        <?php if (isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                                            <button class="btn btn-dark-gray btn-block btn-with-icon"
                                                    id="make-recurring-btn"
                                                    data-recurrence-url="/task/recurrence/panel/<?=(int)$_GET['task_id']?>"
                                                    type="button"
                                                    tabindex="-1">
                                                <?= svgIcon('schedule') ?><?= (!empty($taskScheduleId) ? 'Edit recurring schedule' : 'Make recurring') ?>
                                            </button>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>  <!-- End flex table -->
                        </div> <!-- End right-column -->

                        <input type="hidden" value="<?=$_GET['column_id']?>" name="column_id" class="btn btn-green">
                        <input type="hidden" value="<?=(isset($_GET['task_id']) ? $_GET['task_id'] : '')?>" name="task_id">
                    </div>
                </div>
            </form>
            <!-- Actions -->
            <div class="form-actions">
                <div class="flex-table">
                    <div class="flex-row">
                        <div class="flex-cell">
                        <?php if (isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                            <form id="form_deleteTask" hx-delete="<?=(isset($post_url) ? $post_url : '')?>" hx-target="body" hx-swap="beforeend">
                                <input type="hidden" name="task_id" value="<?=(isset($_GET['task_id']) ? Sanitize::e($_GET['task_id']) : '')?>">
                                <button type="submit" class="btn btn-light-gray btn-hover-red btn-with-icon" tabindex="-1"><?= svgIcon('delete') ?>Delete task</button>
                            </form>
                        <?php endif; ?>
                        </div>
                        <div class="flex-cell flex-vertical-center flex-right">
                            <button type="submit" form="form_addTask" data-form-submit class="btn btn-green btn-with-icon"><?= svgIcon('save') ?><?=($_GET['action'] == 'edit' ? 'Save task' : 'Add task')?></button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End form action -->

            <!-- end of modal -->
        </div>
    </div>
</div>