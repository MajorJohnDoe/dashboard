<?php
use Dashboard\Core\Sanitize;
use Dashboard\Core\HtmxEvents;
use Dashboard\Taskboard\TaskScheduleController;

$scheduleController = new TaskScheduleController($db, $user);
$boardId = (int)$user->getActiveTaskBoard();

// #MARK: DELETE schedule
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['schedule_id'])) {
    $result = $scheduleController->handleDeleteSchedule((int)$_GET['schedule_id']);

    if ($result['success']) {
        triggerResponse(HtmxEvents::successResponse(
            $result['message'],
            [
                HtmxEvents::TASK_BOARD_COLUMN_LIST => true,
                HtmxEvents::REFRESH_SCHEDULE_LIST => true,
                // No-op when the edit modal isn't open; closes it when the
                // delete was issued from inside the edit dialog.
                HtmxEvents::CLOSE_SPECIFIC_MODAL => ['dialog-schedule-edit'],
            ]
        ));
    } else {
        triggerResponse(HtmxEvents::errorResponse($result['message']));
    }
}

// #MARK: TOGGLE schedule (pause/resume)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['schedule_id'])) {
    $result = $scheduleController->handleToggleSchedule((int)$_GET['schedule_id']);

    if ($result['success']) {
        triggerResponse(HtmxEvents::successResponse(
            $result['message'],
            [HtmxEvents::REFRESH_SCHEDULE_LIST => true]
        ));
    } else {
        triggerResponse(HtmxEvents::errorResponse($result['message']));
    }
}

$schedulesResult = $scheduleController->handleGetSchedules($boardId);
$schedules = $schedulesResult['schedules'] ?? [];

// Group schedules by frequency for the 4-column board layout
$frequencies = [
    'daily'   => ['label' => 'Daily',   'icon' => '☀'],
    'weekly'  => ['label' => 'Weekly',  'icon' => '📅'],
    'monthly' => ['label' => 'Monthly', 'icon' => '🗓'],
    'yearly'  => ['label' => 'Yearly',  'icon' => '🎉'],
];
$byFrequency = ['daily' => [], 'weekly' => [], 'monthly' => [], 'yearly' => []];
foreach ($schedules as $schedule) {
    $freq = $schedule['frequency'] ?? 'daily';
    $byFrequency[$freq][] = $schedule;
}
$totalSchedules = count($schedules);
?>
<div id="dialog-schedule-list"
     class="modal-container"
     hx-get="/schedule/dialog/init"
     hx-trigger="refreshScheduleList from:body"
     hx-target="#dialog-schedule-list"
     hx-swap="outerHTML">

    <div class="dialog dialog-lg schedule-dialog">
        <div class="dialog-header">
            <span>Recurring tasks<?= $totalSchedules > 0 ? ' (' . $totalSchedules . ')' : '' ?></span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
            <div class="schedule-board">

                <?php foreach ($frequencies as $freqKey => $freqMeta): ?>
                    <div class="schedule-column">
                        <div class="schedule-column-header">
                            <span class="schedule-column-icon"><?= $freqMeta['icon'] ?></span>
                            <span class="schedule-column-title"><?= $freqMeta['label'] ?></span>
                            <span class="schedule-column-count"><?= count($byFrequency[$freqKey]) ?></span>
                        </div>
                        <div class="schedule-column-body">
                            <?php if (empty($byFrequency[$freqKey])): ?>
                                <div class="schedule-column-empty">
                                    No <?= strtolower($freqMeta['label']) ?> schedules
                                </div>
                            <?php endif; ?>

                            <?php foreach ($byFrequency[$freqKey] as $schedule): ?>
                                <?php
                                    $isActive = (bool)$schedule['is_active'];
                                    $nextRun = $schedule['next_run'] ? date('M j, Y', strtotime($schedule['next_run'])) : null;
                                    $ruleSummary = TaskScheduleController::describeRule($schedule);
                                    $endSummary = TaskScheduleController::describeEnd($schedule);
                                    $sid = (int)$schedule['schedule_id'];
                                    $priorityClass = [0 => 'priority-lowest', 1 => 'priority-low', 2 => 'priority-alarming', 3 => 'priority-critical', 4 => 'priority-highest'][(int)$schedule['task_priority']] ?? 'priority-lowest';
                                ?>
                                <div class="tm-task <?= $priorityClass ?> schedule-card <?= $isActive ? '' : 'schedule-card-paused' ?> open-modal-btn"
                                     data-modal-target="#dialog-schedule-edit"
                                     hx-get="/schedule/dialog/edit/<?= (int)$schedule['board_id'] ?>/<?= $sid ?>"
                                     hx-target="body"
                                     hx-swap="beforeend">
                                    <div style="flex: 1; min-width: 0;">
                                        <div class="title" title="<?= Sanitize::e(html_entity_decode($schedule['task_title'])) ?>">
                                            <?= Sanitize::e(html_entity_decode($schedule['task_title'])) ?>
                                        </div>
                                        <div class="schedule-rule-text">
                                            <?= Sanitize::e($ruleSummary) ?>
                                            <?php if (!empty($endSummary)): ?>
                                                &middot; <span class="schedule-end"><?= Sanitize::e($endSummary) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="schedule-card-sub">
                                            <?php if ($isActive): ?>
                                                <span class="schedule-status-dot <?= $isActive ? 'schedule-status-active' : 'schedule-status-paused' ?>"
                                                      title="<?= $isActive ? 'Active' : 'Paused' ?>"></span>
                                                <span class="schedule-next">
                                                    <?php if ($nextRun): ?>Next <strong><?= Sanitize::e($nextRun) ?></strong><?php endif; ?>
                                                </span>
                                                <span class="schedule-count"><?= (int)$schedule['run_count'] ?> run<?= (int)$schedule['run_count'] === 1 ? '' : 's' ?></span>
                                            <?php else: ?>
                                                <span class="schedule-paused-label">Paused</span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- hx-on:click (single colon) = NATIVE click listener. stopPropagation
                                             prevents the bubbled click from triggering the card's hx-get (edit modal).
                                             Double-colon hx-on::click would bind htmx:click instead and NOT work. -->
                                        <div class="schedule-card-actions" hx-on:click="event.stopPropagation()">
                                            <form hx-post="/schedule/toggle/<?= $sid ?>" hx-target="this" hx-swap="none">
                                                <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
                                                <button type="submit" class="btn btn-dark-gray" title="<?= $isActive ? 'Pause schedule' : 'Resume schedule' ?>">
                                                    <?= $isActive ? 'Pause' : 'Resume' ?>
                                                </button>
                                            </form>
                                            <form hx-delete="/schedule/delete/<?= $sid ?>"
                                                  hx-target="this" hx-swap="none"
                                                  hx-confirm="Delete this schedule? Tasks already created will not be removed.">
                                                <?= \Dashboard\Core\CsrfProtection::getTokenField() ?>
                                                <button type="submit" class="btn btn-light-gray btn-hover-red">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>

            <?php if ($totalSchedules === 0): ?>
                <div class="schedule-empty-state">
                    <p>No recurring tasks yet</p>
                    <p>Create a schedule to automatically add recurring tasks to this board — like weekly reviews or monthly reports.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <div class="flex-table">
                <div class="flex-cell schedule-footer-hint">
                    Schedules spawn their task automatically when due — just keep visiting this board.
                </div>
                <div class="flex-cell flex-vertical-center flex-right">
                    <button class="btn btn-green"
                            hx-get="/schedule/dialog/new/<?= $boardId ?>"
                            hx-target="body"
                            hx-swap="beforeend">+ New recurring task</button>
                </div>
            </div>
        </div>
    </div>
</div>
