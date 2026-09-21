<?php
use Dashboard\Core\Sanitize;
use Dashboard\Core\HtmxEvents;
use Dashboard\Core\AttachmentService;
use Dashboard\Taskboard\TaskScheduleController;
use Dashboard\Taskboard\TaskSchedule;
use Dashboard\Taskboard\ColumnController;

$scheduleController = new TaskScheduleController($db, $user);
// Route is /schedule/dialog/edit/:board_id/:schedule_id — 'edit' is a literal
// path segment, so there is no :action param. Mode is derived from the
// presence of schedule_id instead.
$action = isset($_GET['schedule_id']) && (int)$_GET['schedule_id'] > 0 ? 'edit' : 'new';
$boardId = (int)$_GET['board_id'];
$scheduleId = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;

$postUrl = $action === 'edit'
    ? "/schedule/dialog/edit/{$boardId}/{$scheduleId}"
    : "/schedule/dialog/new/{$boardId}";

// Checklist rows post as checklist[i][status] / checklist[i][description]
// (same field names as the task dialog). Normalize them into the
// task_checklist template field, which TaskSchedule::normalizeChecklist()
// accepts as an array or a JSON string.
//
// When NO checklist inputs are posted (no rows added, or the user removed
// them all) an empty string is sent instead: normalizeChecklist('') returns
// null, which clears the stored template checklist. Without this the
// controller's `$data[...] ?? $existing[...]` fallback would keep the old
// checklist forever.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['checklist']) && is_array($_POST['checklist'])) {
        $_POST['task_checklist'] = $_POST['checklist'];
    } else {
        $_POST['task_checklist'] = '';
    }
}

// #MARK: CREATE schedule - form post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'new') {
    $_POST['board_id'] = $boardId;
    $result = $scheduleController->handleCreateSchedule($_POST);

    if ($result['success']) {
        triggerResponse(HtmxEvents::successResponse(
            $result['message'],
            [
                HtmxEvents::TASK_BOARD_COLUMN_LIST => true,
                HtmxEvents::CLOSE_SPECIFIC_MODAL => ['dialog-schedule-edit', 'dialog-schedule-list'],
                HtmxEvents::REFRESH_SCHEDULE_LIST => true,
            ]
        ));
    } else {
        triggerResponse(HtmxEvents::errorResponse($result['message']));
    }
}

// #MARK: UPDATE schedule - form post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $_POST['schedule_id'] = $scheduleId;
    $result = $scheduleController->handleUpdateSchedule($_POST);

    if ($result['success']) {
        triggerResponse(HtmxEvents::successResponse(
            $result['message'],
            [
                HtmxEvents::TASK_BOARD_COLUMN_LIST => true,
                HtmxEvents::CLOSE_SPECIFIC_MODAL => ['dialog-schedule-edit', 'dialog-schedule-list'],
                HtmxEvents::REFRESH_SCHEDULE_LIST => true,
            ]
        ));
    } else {
        triggerResponse(HtmxEvents::errorResponse($result['message']));
    }
}

// Load existing schedule for edit
$schedule = [];
if ($action === 'edit' && $scheduleId > 0) {
    $result = $scheduleController->handleGetSchedule($scheduleId);
    if ($result['success']) {
        $schedule = $result['schedule'];
    } else {
        triggerResponse(HtmxEvents::errorResponse($result['message']));
    }
}

// Form state (existing values or sensible defaults)
$title        = $schedule['task_title'] ?? '';
$description  = $schedule['task_desc'] ?? '';
$priority     = (int)($schedule['task_priority'] ?? 0);
$columnId     = (int)($schedule['column_id'] ?? 0);
$frequency    = $schedule['frequency'] ?? 'daily';
$interval     = (int)($schedule['interval'] ?? 1);
$weekdays     = array_map('intval', explode(',', (string)($schedule['weekdays'] ?? '')));
$monthDay     = (int)($schedule['month_day'] ?? 1);
$nthWeekday   = $schedule['nth_weekday'] ?? '1:1';
$startDate    = $schedule['start_date'] ?? date('Y-m-d');
$endType      = $schedule['end_type'] ?? 'never';
$endCount     = (int)($schedule['end_count'] ?? 5);
$endDate      = $schedule['end_date'] ?? date('Y-m-d', strtotime('+1 year'));
$selectedLabels = [];
if (!empty($schedule['labels'])) {
    $decoded = json_decode((string)$schedule['labels'], true);
    if (is_array($decoded)) {
        $selectedLabels = array_map('intval', $decoded);
    }
}

// Checklist template items (same shape as tm_task.task_checklist)
$checklistItems = [];
if (!empty($schedule['task_checklist'])) {
    $decoded = json_decode((string)$schedule['task_checklist'], true);
    if (is_array($decoded)) {
        $checklistItems = $decoded;
    }
}

// Columns for target selection
$columnController = new ColumnController($db, $user);
$columnsResult = $columnController->getColumnsForBoard($boardId);

// Selected label details for rendering badges
$selectedLabelDetails = [];
if (!empty($selectedLabels)) {
    $boardLabels = new \Dashboard\Taskboard\Board($db);
    $allLabels = $boardLabels->loadBoardLabels($boardId);
    foreach ($allLabels as $label) {
        if (in_array((int)$label['id'], $selectedLabels, true)) {
            $selectedLabelDetails[] = $label;
        }
    }
}

$weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Recurrence-rule form partial (form.recurrence.php) expects these variables.
$onlyIfCompleted = !empty($schedule['only_if_completed']);
$columns = $columnsResult;
$idPrefix = 'schedule';
?>
<div id="dialog-schedule-edit"
     class="modal-container"
     hx-get="<?= $postUrl ?>"
     hx-trigger="refreshModal from:body"
     hx-target="#dialog-schedule-edit"
     hx-swap="outerHTML">

    <div class="dialog dialog-lg">
        <div class="dialog-header">
            <span><?= $action === 'edit' ? 'Edit recurring task' : 'New recurring task' ?></span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
            <form id="schedule-form" method="POST" hx-post="<?= $postUrl ?>" hx-target="#dialog-schedule-edit .formOuter" hx-swap="beforeend">
                <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
                <div class="nice-form-group">
                    <div class="edit-grid" style="grid-template-columns: 4fr 2fr;">
                        <!-- Left Column: task template -->
                        <div class="left-column">
                            <div class="flex-table">
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <label for="task_title">Task title:</label>
                                        <input type="text" name="task_title" id="task_title" autocomplete="off" autofocus
                                               value="<?= Sanitize::e($title) ?>">
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
                                    <div class="flex-cell flex-cell-vcenter" id="selectedLabelsContainer">
                                        <?php foreach ($selectedLabelDetails as $label): ?>
                                            <input type="hidden" name="selectedLabels[]" value="<?= (int)$label['id'] ?>">
                                            <span style="background-color: <?= Sanitize::e($label['label_color']) ?>;"><?= Sanitize::e($label['label_name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php
                                // ---- Tab system: Description / Checklist / Attachments ----
                                // Same tab bar as the task dialog (identical markup and badges),
                                // with two deliberate differences:
                                //  1. Every tab stays visible. The task dialog hides its empty
                                //     conditional tabs because its sidebar has "switch to tab"
                                //     buttons to bring them back; this dialog's sidebar holds the
                                //     recurrence rule instead, so a hidden tab would be unreachable.
                                //  2. The saved drag order applies to EDIT mode only — a new
                                //     schedule always renders the default order below and its tab
                                //     bar carries no order context, so it can neither inherit nor
                                //     silently overwrite the layout the user set while editing.
                                // Attachments on a template are copied to every task the schedule
                                // spawns (TaskSchedule::spawnTaskFromSchedule), which is the point
                                // of attaching them to a recurring task.
                                $scheduleDefaultTabs = ['description', 'checklist', 'attachments'];
                                $scheduleTabOrder = ($action === 'edit')
                                    ? (new \Dashboard\Core\UiPreferenceService($db))
                                        ->getTabOrder((int)$user->getUserId(), 'schedule', $scheduleDefaultTabs)
                                    : $scheduleDefaultTabs;
                                // Attachments need a persisted schedule to belong to.
                                $scheduleChecklistCount = count($checklistItems);
                                $scheduleAttachmentCount = ($action === 'edit' && $scheduleId > 0)
                                    ? (new AttachmentService($db))->countForItem((int)$user->getUserId(), $scheduleId, 'schedule')
                                    : 0;

                                // Opening tab = the first tab of the saved order that actually
                                // holds something. Every tab stays rendered (the bar always shows
                                // the user's order), but the dialog must never open on an empty
                                // placeholder: a brand-new schedule has no checklist items and
                                // nothing to attach to yet, so it starts on Description even when
                                // Checklist/Attachments are ordered first.
                                $scheduleActiveTab = \Dashboard\Core\UiPreferenceService::defaultTab($scheduleTabOrder, [
                                    'description' => true,
                                    'checklist'   => $scheduleChecklistCount > 0,
                                    'attachments' => $scheduleAttachmentCount > 0,
                                ]);
                                $scheduleTabButtons = [];

                                ob_start(); ?>
                                    <button type="button" class="modal-tab<?= $scheduleActiveTab === 'description' ? ' active' : '' ?>" data-tab="description">
                                        <?= svgIcon('description', ['class' => 'modal-tab-icon']) ?>Description
                                    </button>
                                <?php $scheduleTabButtons['description'] = ob_get_clean();

                                ob_start(); ?>
                                    <button type="button" class="modal-tab<?= $scheduleActiveTab === 'checklist' ? ' active' : '' ?>" data-tab="checklist">
                                        <?= svgIcon('check', ['class' => 'modal-tab-icon']) ?>Checklist<span class="modal-tab-badge" data-tab-badge="checklist"><?= $scheduleChecklistCount ?></span>
                                    </button>
                                <?php $scheduleTabButtons['checklist'] = ob_get_clean();

                                ob_start(); ?>
                                    <button type="button" class="modal-tab<?= $scheduleActiveTab === 'attachments' ? ' active' : '' ?>" data-tab="attachments">
                                        <?= svgIcon('attachments', ['class' => 'modal-tab-icon']) ?>Attachments<span class="modal-tab-badge" data-tab-badge="attachments"><?= $scheduleAttachmentCount ?></span>
                                    </button>
                                <?php $scheduleTabButtons['attachments'] = ob_get_clean();
                                ?>
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <div class="modal-tabs schedule-modal-tabs" data-modal-tabs<?= $action === 'edit' ? ' data-tab-order-context="schedule"' : '' ?>>
                                            <?php foreach ($scheduleTabOrder as $tabName): ?>
                                                <?= $scheduleTabButtons[$tabName] ?? '' ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-tab-pane modal-tab-pane-flex<?= $scheduleActiveTab === 'description' ? ' active' : '' ?>" data-tab-pane="description">
                                    <div class="flex-row">
                                        <div class="flex-cell">
                                            <textarea name="task_desc" id="task_desc" class="tinymce_editor tinymce-hidden" aria-hidden="true"><?= Sanitize::e($description) ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-tab-pane<?= $scheduleActiveTab === 'checklist' ? ' active' : '' ?>" data-tab-pane="checklist">
                                    <div class="flex-row">
                                        <?php
                                        // Unified checklist UI: the same partial the task edit
                                        // dialog renders (views/core/partial/checklist.php). Only
                                        // the empty-state text differs — a template with no items
                                        // is a meaningful state here ("spawned tasks won't
                                        // include one"), while the task dialog has no placeholder.
                                        $checklistEmptyText = "No checklist items — spawned tasks won't include one.";
                                        include BASE_DIR . '/views/core/partial/checklist.php';
                                        ?>
                                    </div>
                                </div>

                                <div class="modal-tab-pane<?= $scheduleActiveTab === 'attachments' ? ' active' : '' ?>" data-tab-pane="attachments">
                                    <?php if ($action === 'edit' && $scheduleId > 0): ?>
                                        <?php
                                        // Edit mode only: a schedule must exist before files can
                                        // be attached to it (same rule as the note/job dialogs).
                                        $attachmentItemType = 'schedule';
                                        $attachmentItemId = $scheduleId;
                                        $attachmentCount = $scheduleAttachmentCount;
                                        include BASE_DIR . '/views/core/partial/attachments.php';
                                        ?>
                                    <?php else: ?>
                                        <div class="attachment-list-empty">Create the schedule first, then you can attach files.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: recurrence rule (shared partial) -->
                        <div class="right-column">
                            <?php include BASE_DIR . '/views/taskboard/partial/schedule/form.recurrence.php'; ?>
                        </div>

                        <input type="hidden" name="board_id" value="<?= $boardId ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="schedule_id" value="<?= $scheduleId ?>">
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="form-actions">
            <div class="flex-table">
                <div class="flex-row">
                    <div class="flex-cell flex-vertical-center">
                        <?php if ($action === 'edit'): ?>
                            <form hx-delete="/schedule/delete/<?= $scheduleId ?>" hx-target="this" hx-swap="none"
                                  hx-confirm="Delete this schedule? Tasks already created will not be removed.">
                                <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
                                <button type="submit" class="btn btn-light-gray btn-hover-red">Delete schedule</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="flex-cell flex-vertical-center flex-right">
                        <input type="submit" value="<?= $action === 'edit' ? 'Save schedule' : 'Create schedule' ?>" form="schedule-form" class="btn btn-green">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
