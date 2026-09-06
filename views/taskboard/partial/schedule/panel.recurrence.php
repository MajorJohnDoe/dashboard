<?php
use Dashboard\Core\Sanitize;
use Dashboard\Core\HtmxEvents;
use Dashboard\Taskboard\TaskScheduleController;
use Dashboard\Taskboard\ColumnController;
use Dashboard\Taskboard\Task;

// Route: GET/POST /task/recurrence/panel/:task_id
// Slide-out panel with the recurrence-rule form, opened from the task edit
// modal ("Make recurring" / "Edit recurring schedule") or the task context
// menu. Saving converts the task into the schedule's first occurrence.

$taskId = (int)$_GET['task_id'];
$taskModel = new Task($db);
$panelId = 'panel-task-recurrence';

// #MARK: SAVE recurrence - form post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $taskId > 0) {
    $_POST['task_id'] = $taskId;
    $scheduleController = new TaskScheduleController($db, $user);
    try {
        $result = $scheduleController->handleSaveScheduleFromTask($_POST);
    } catch (\Throwable $e) {
        error_log('Recurrence save failed for task ' . $taskId . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine());
        $result = ['success' => false, 'message' => 'Server error while saving the schedule. Check the PHP error log for details.'];
    }

    if ($result['success']) {
        triggerResponse(HtmxEvents::successResponse($result['message'], [
            HtmxEvents::TASK_BOARD_COLUMN_LIST => true,
            HtmxEvents::REFRESH_SCHEDULE_LIST => true,
            HtmxEvents::REFRESH_MODAL => true,
            HtmxEvents::CLOSE_RECURRENCE_PANEL => true,
        ]));
    } else {
        triggerResponse(HtmxEvents::errorResponse($result['message']));
    }
}

// #MARK: LOAD panel - get request
if ($taskId <= 0 || !$taskModel->validateTaskOwnership($user->getUserId(), $taskId)
    || !$taskModel->loadTaskDetails($taskId, $user->getUserId())) {
    triggerResponse(HtmxEvents::errorResponse('Task not found.'));
}

// Existing linked schedule (edit mode) or task defaults (create mode)
$schedule = [];
$scheduleId = $taskModel->getTaskScheduleId();
$scheduleController = new TaskScheduleController($db, $user);
if ($scheduleId > 0) {
    $result = $scheduleController->handleGetSchedule($scheduleId);
    if ($result['success']) {
        $schedule = $result['schedule'];
    }
}

// Form state — linked schedule values win, then task values, then defaults
$priority     = (int)($schedule['task_priority'] ?? $taskModel->getTaskPriority() ?? 0);
$columnId     = (int)($schedule['column_id'] ?? $taskModel->getTaskColumnId() ?? 0);
$frequency    = $schedule['frequency'] ?? 'daily';
$interval     = (int)($schedule['interval'] ?? 1);
$weekdays     = array_map('intval', explode(',', (string)($schedule['weekdays'] ?? '')));
$monthDay     = (int)($schedule['month_day'] ?? 1);
$nthWeekday   = $schedule['nth_weekday'] ?? '';
$endType      = $schedule['end_type'] ?? 'never';
$endCount     = (int)($schedule['end_count'] ?? 5);
$endDate      = $schedule['end_date'] ?? date('Y-m-d', strtotime('+1 year'));
$onlyIfCompleted = !empty($schedule['only_if_completed']);
$taskTitle    = $schedule['task_title'] ?? $taskModel->getTaskTitle();
$isEditing    = $scheduleId > 0;

// Columns for target selection
$columnController = new ColumnController($db, $user);
$columns = $columnController->getColumnsForBoard($taskModel->getTaskBoardId());

$idPrefix = 'recurrence';
?>
<aside id="<?= $panelId ?>" class="slide-panel" data-slide-panel-host="#dialog-column-add-task .dialog" role="dialog" aria-label="Recurring schedule">
        <div class="slide-panel-header">
            <span><?= $isEditing ? 'Edit recurring schedule' : 'Make recurring' ?></span>
            <button type="button" class="btn" data-slide-panel-close title="Close">X</button>
        </div>

        <div class="slide-panel-body">
            <?php if ($isEditing): ?>
                <p class="slide-panel-hint">
                    This task is recurring. Changes apply to the schedule — future copies will follow the new rule.
                </p>
            <?php else: ?>
                <p class="slide-panel-hint">
                    “<strong><?= Sanitize::e(html_entity_decode($taskTitle)) ?></strong>” will repeat automatically. This task counts as the first occurrence.
                </p>
            <?php endif; ?>

            <form id="<?= $idPrefix ?>-form" method="POST" hx-post="/task/recurrence/save/<?= $taskId ?>" hx-target="#<?= $idPrefix ?>-form" hx-swap="none">
                <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
                <input type="hidden" name="task_id" value="<?= $taskId ?>">
                <input type="hidden" name="task_title" value="<?= Sanitize::e($taskTitle) ?>">

                <div class="nice-form-group">
                <?php
                // No Priority here — the task dialog already has its own
                // priority selector; the schedule template inherits the task's.
                $showPriority = false;
                include BASE_DIR . '/views/taskboard/partial/schedule/form.recurrence.php';
                ?>
                </div>
            </form>
        </div>

        <div class="slide-panel-footer">
            <button type="button" class="btn btn-light-gray" data-slide-panel-close>Cancel</button>
            <input type="submit" value="<?= $isEditing ? 'Save schedule' : 'Make recurring' ?>" form="<?= $idPrefix ?>-form" class="btn btn-green">
        </div>
</aside>
<script>
    // Delegate the open/close lifecycle to the reusable SlideOutPanel
    // component (assets/js/slide.panel.js). The panel moves itself into
    // the task dialog (data-slide-panel-host) and slides out from behind
    // it via pure CSS (left: 100%, z-index: -1).
    SlideOutPanel.setup('<?= $panelId ?>');
</script>