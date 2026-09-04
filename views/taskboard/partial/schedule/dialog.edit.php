<?php
use Dashboard\Core\HtmxEvents;
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
                                               value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell flex-cell-shrink flex-vertical-center" style="position: relative;">
                                        <div style="position: relative;">
                                            <input
                                                class="fa-solid fa-tags"
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
                                            <span style="background-color: <?= htmlspecialchars($label['label_color'], ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars($label['label_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <label for="task_desc">Task description:</label>
                                        <textarea name="task_desc" id="task_desc" class="tinymce_editor tinymce-hidden" aria-hidden="true"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
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
                <div class="flex-cell"></div>
                <div class="flex-cell flex-vertical-center flex-right">
                    <input type="submit" value="<?= $action === 'edit' ? 'Save schedule' : 'Create schedule' ?>" form="schedule-form" class="btn btn-green">
                </div>
            </div>
        </div>
    </div>
</div>
