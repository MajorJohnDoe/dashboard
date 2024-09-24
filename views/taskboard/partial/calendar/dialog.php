<?php
use Dashboard\Taskboard\HistoryCalendar;
use Dashboard\Taskboard\TaskController;

function getDateInfo($inputDate = null) {
    $currentDate = $inputDate ? new \DateTime($inputDate) : new \DateTime();
    $currentDate->modify('monday this week');

    $prevWeek = clone $currentDate;
    $prevWeek->modify('-1 week');
    $nextWeek = clone $currentDate;
    $nextWeek->modify('+1 week');

    return [
        'currentDate' => $currentDate,
        'prevWeek' => $prevWeek,
        'nextWeek' => $nextWeek,
        'isNextWeekFuture' => $nextWeek > new \DateTime(),
        'weekNumber' => $currentDate->format('W'),
        'year' => $currentDate->format('Y')
    ];
}

function getTasks($user, $db, $weekDates) {
    $taskObj = new TaskController($db, $user);
    $tasks = [];

    foreach ($weekDates as $date) {
        $result = $taskObj->handleGetTasksUsingDate($user->getActiveTaskBoard(), $date);
        
        // Check if the result is successful and contains tasks
        if ($result['success'] && is_array($result['tasks'])) {
            $dateTasks = $result['tasks'];
            foreach ($dateTasks as &$task) {
                $taskObj->loadTaskDataById($task['task_id'], $user->user_id());
                $task['labels'] = $taskObj->getTaskLabels();
                $task['completion_rate'] = $taskObj->getChecklistCompletionRate();
            }
            $tasks[$date] = $dateTasks;
        } else {
            $tasks[$date] = []; // No tasks for this date
        }
    }

    return $tasks;
}

// Main logic
$dateInfo = getDateInfo($_GET['date'] ?? null);
$calendar = new HistoryCalendar($dateInfo['currentDate']->format('Y-m-d'));
$weekDates = $calendar->getDatesForWeekNumber($dateInfo['weekNumber'], $dateInfo['year']);
$tasks = getTasks($user, $db, $weekDates);
$calendar->setTasks($tasks);

$isLoadingModal = isset($_GET['init']) && $_GET['init'] == 'true';

// HTML output
?>

<?php if ($isLoadingModal): ?>
<div id="dialog-view-task-history" class="modal-container">
    <div class="dialog" style="width: 90%; height: 90%;">
        <div class="dialog-header">
            <span>Task history</span>
            <button class="close-modal-btn btn">X</button>
        </div>
<?php endif; ?>

        <div class="formOuter" id="task-history-content" style="max-height: 100%; overflow: hidden;"
            hx-get="/calendar/dialog/date/<?= $dateInfo['currentDate']->format('Y-m-d') ?>"
            hx-trigger="refreshTaskHistory from:body"
            hx-target="#task-history-content"
            hx-swap="outerHTML"
        >
            
            <div class="calendar-navigation">
                <button class="open-modal-btn btn btn-green"
                        hx-get="/calendar/dialog/date/<?= $dateInfo['prevWeek']->format('Y-m-d') ?>"
                        hx-target="#task-history-content"
                        hx-swap="outerHTML">
                    Prev
                </button>
                <?= $dateInfo['currentDate']->format('Y-m-d') ?><br>
                <button class="open-modal-btn btn btn-green"
                        hx-get="/calendar/dialog/date/<?= $dateInfo['nextWeek']->format('Y-m-d') ?>"
                        hx-target="#task-history-content"
                        hx-swap="outerHTML"
                        <?= $dateInfo['isNextWeekFuture'] ? 'disabled' : '' ?>>
                    Next
                </button>
            </div>
            
            <?php $calendar->printCalendar(); ?>

        </div>

<?php if ($isLoadingModal): ?>
    </div>
</div>
<?php endif; ?>

