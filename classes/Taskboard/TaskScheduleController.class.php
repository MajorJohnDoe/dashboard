<?php
namespace Dashboard\Taskboard;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\ItemImageService;
use Dashboard\Core\User;

/**
 * Controller for recurring task schedules. All handlers validate board/column
 * ownership and return ['success' => bool, 'message' => string] arrays —
 * views are responsible for firing HTMX triggers.
 */
class TaskScheduleController
{
    private DatabaseInterface $db;
    private Board $board;
    private TaskSchedule $schedules;
    private User $user;

    public function __construct(DatabaseInterface $db, User $user)
    {
        $this->db = $db;
        $this->board = new Board($db);
        $this->schedules = new TaskSchedule($db);
        $this->user = $user;
    }

    /**
     * Create a schedule from POST data.
     */
    public function handleCreateSchedule(array $postData): array
    {
        $boardId = (int)($postData['board_id'] ?? 0);
        if (!$this->board->validateBoardWriteAccess($this->user->getUserId(), $boardId)) {
            return ['success' => false, 'message' => 'You do not have write access to this board.'];
        }

        // The dialog no longer shows a start-date input, so default it here
        // (validateScheduleData() defaults its own local copy — that default
        // must not be relied on by callers).
        if (empty(trim((string)($postData['start_date'] ?? '')))) {
            $postData['start_date'] = date('Y-m-d');
        }

        $errors = $this->validateScheduleData($postData, $boardId);
        if ($errors !== null) {
            return $errors;
        }

        $frequency = $postData['frequency'] ?? 'daily';

        // Yearly uses the yearly day input (the monthly day select is hidden then)
        $monthDay = $postData['month_day'] ?? null;
        if ($frequency === 'yearly' && isset($postData['month_day_yearly'])) {
            $monthDay = $postData['month_day_yearly'];
        }

        $scheduleId = $this->schedules->addSchedule([
            'board_id'      => $boardId,
            'user_id'       => $this->user->getUserId(),
            'column_id'     => (int)($postData['column_id'] ?? 0),
            'task_title'    => $postData['task_title'] ?? '',
            'task_desc'     => $postData['task_desc'] ?? '',
            'task_checklist'=> $postData['task_checklist'] ?? null,
            'task_priority' => (int)($postData['task_priority'] ?? 0),
            'labels'        => $postData['selectedLabels'] ?? null,
            'frequency'     => $frequency,
            'interval'      => (int)($postData['interval'] ?? 1),
            'weekdays'      => $this->collectWeekdays($postData),
            'month_day'     => $monthDay,
            'nth_weekday'   => $postData['nth_weekday'] ?? null,
            'start_date'    => $postData['start_date'] ?? '',
            'end_type'      => $postData['end_type'] ?? 'never',
            'end_count'     => (int)($postData['end_count'] ?? 0),
            'only_if_completed' => !empty($postData['only_if_completed']) ? 1 : 0,
            'end_date'      => $postData['end_date'] ?? '',
        ]);

        if ($scheduleId === false) {
            return ['success' => false, 'message' => 'Failed to create schedule. Check the recurrence rule (e.g. end date must be in the future).'];
        }

        return ['success' => true, 'message' => 'Recurring task created successfully', 'schedule_id' => $scheduleId];
    }

    /**
     * Update a schedule from POST data.
     */
    public function handleUpdateSchedule(array $postData): array
    {
        $scheduleId = (int)($postData['schedule_id'] ?? 0);
        $schedule = $this->schedules->getScheduleById($scheduleId);

        if (empty($schedule)) {
            return ['success' => false, 'message' => 'Schedule not found.'];
        }

        $boardId = (int)$schedule[0]['board_id'];
        if (!$this->board->validateBoardWriteAccess($this->user->getUserId(), $boardId)) {
            return ['success' => false, 'message' => 'You do not have write access to this board.'];
        }

        $errors = $this->validateScheduleData($postData, $boardId);
        if ($errors !== null) {
            return $errors;
        }

        $frequency = $postData['frequency'] ?? 'daily';

        // The dialog no longer shows a start-date input: keep the stored
        // anchor on edit; validation defaults it to today on create.
        if (empty(trim((string)($postData['start_date'] ?? '')))) {
            $postData['start_date'] = $schedule[0]['start_date'];
        }

        // Yearly uses the yearly day input (the monthly day select is hidden then)
        $monthDay = $postData['month_day'] ?? null;
        if ($frequency === 'yearly' && isset($postData['month_day_yearly'])) {
            $monthDay = $postData['month_day_yearly'];
        }

        $result = $this->schedules->updateSchedule($scheduleId, [
            'column_id'     => (int)($postData['column_id'] ?? 0),
            'task_title'    => $postData['task_title'] ?? '',
            'task_desc'     => $postData['task_desc'] ?? '',
            'task_checklist'=> $postData['task_checklist'] ?? null,
            'task_priority' => (int)($postData['task_priority'] ?? 0),
            'labels'        => $postData['selectedLabels'] ?? null,
            'frequency'     => $frequency,
            'interval'      => (int)($postData['interval'] ?? 1),
            'weekdays'      => $this->collectWeekdays($postData),
            'month_day'     => $monthDay,
            'nth_weekday'   => $postData['nth_weekday'] ?? null,
            'start_date'    => $postData['start_date'] ?? '',
            'end_type'      => $postData['end_type'] ?? 'never',
            'end_count'     => (int)($postData['end_count'] ?? 0),
            'only_if_completed' => !empty($postData['only_if_completed']) ? 1 : 0,
            'end_date'      => $postData['end_date'] ?? '',
        ]);

        return $result
            ? ['success' => true, 'message' => 'Recurring task updated successfully']
            : ['success' => false, 'message' => 'Failed to update schedule.'];
    }

    /**
     * Save a recurrence rule for an existing task ("Make recurring" from the
     * task edit modal / context menu).
     *
     * The task's title/description/priority/labels become the schedule template;
     * the posted recurrence fields (frequency, interval, weekdays, ...) define
     * the rule. If the task is already linked to a schedule, that schedule is
     * updated; otherwise a new schedule is created and the task is linked via
     * tm_task.schedule_id so it counts as the first occurrence (no duplicate
     * task is spawned).
     *
     * @param array $postData Recurrence form fields + task_id (hidden field or GET param).
     */
    public function handleSaveScheduleFromTask(array $postData): array
    {
        $taskId = (int)($postData['task_id'] ?? 0);
        if ($taskId <= 0) {
            return ['success' => false, 'message' => 'Missing task.'];
        }

        $taskModel = new Task($this->db);
        if (!$taskModel->validateTaskOwnership($this->user->getUserId(), $taskId)) {
            return ['success' => false, 'message' => 'Unauthorized: User does not own this task.'];
        }

        if (!$taskModel->loadTaskDetails($taskId, $this->user->getUserId())) {
            return ['success' => false, 'message' => 'Task not found.'];
        }

        // Build schedule data from the task + posted recurrence fields.
        // The panel form has no label picker, so reuse the task's labels.
        $taskLabels = $taskModel->getTaskLabels();
        $labelIds = array_values(array_filter(array_map(
            fn($label) => (int)($label['label_id'] ?? $label['id'] ?? 0),
            is_array($taskLabels) ? $taskLabels : []
        ), fn($id) => $id > 0));
        $postData['selectedLabels'] = $labelIds;
        $postData['task_title'] = $taskModel->getTaskTitle();
        $postData['task_priority'] = $postData['task_priority'] ?? $taskModel->getTaskPriority();
        $postData['start_date'] = $postData['start_date'] ?? date('Y-m-d');

        // Rule-only validation: reuse the standard validation minus title
        // checks that the task title already satisfies.
        $errors = $this->validateScheduleData($postData, (int)$taskModel->getTaskBoardId());
        if ($errors !== null) {
            return $errors;
        }

        $frequency = $postData['frequency'] ?? 'daily';
        $monthDay = $postData['month_day'] ?? null;
        if ($frequency === 'yearly' && isset($postData['month_day_yearly'])) {
            $monthDay = $postData['month_day_yearly'];
        }

        // Already recurring? Update the linked schedule. A dangling
        // schedule_id (schedule deleted without unlinking, e.g. by older
        // code) is treated as "not recurring" — a new schedule is created.
        $existingScheduleId = $taskModel->getTaskScheduleId();
        if ($existingScheduleId > 0 && !empty($this->schedules->getScheduleById($existingScheduleId))) {
            $postData['schedule_id'] = $existingScheduleId;
            $result = $this->handleUpdateSchedule($postData);
            if ($result['success']) {
                $result['schedule_id'] = $existingScheduleId;
            }
            return $result;
        }

        // When converting an existing task, the task itself counts as the 1st occurrence.
        // Therefore, calculate next_run strictly after today and record run_count = 1.
        $nextRun = $this->schedules->calculateNextRun(
            $frequency,
            (int)($postData['interval'] ?? 1),
            $postData['start_date'],
            $this->collectWeekdays($postData),
            $monthDay,
            $postData['nth_weekday'] ?? null,
            $postData['end_type'] ?? 'never',
            (int)($postData['end_count'] ?? 0),
            $postData['end_date'] ?? '9999-12-31',
            1,
            date('Y-m-d')
        );

        $scheduleId = $this->schedules->addSchedule([
            'board_id'      => $taskModel->getTaskBoardId(),
            'user_id'       => $this->user->getUserId(),
            'column_id'     => (int)($postData['column_id'] ?? $taskModel->getTaskColumnId()),
            'task_title'    => $postData['task_title'],
            'task_desc'     => $taskModel->getTaskDescription(),
            'task_checklist'=> $taskModel->getTaskChecklist(),
            'task_priority' => (int)$postData['task_priority'],
            'labels'        => $postData['selectedLabels'],
            'frequency'     => $frequency,
            'interval'      => (int)($postData['interval'] ?? 1),
            'weekdays'      => $this->collectWeekdays($postData),
            'month_day'     => $monthDay,
            'nth_weekday'   => $postData['nth_weekday'] ?? null,
            'start_date'    => $postData['start_date'],
            'end_type'      => $postData['end_type'] ?? 'never',
            'end_count'     => (int)($postData['end_count'] ?? 0),
            'only_if_completed' => !empty($postData['only_if_completed']) ? 1 : 0,
            'end_date'      => $postData['end_date'] ?? '',
            'next_run'      => $nextRun,
            'run_count'     => 1,
            'last_run'      => date('Y-m-d H:i:s'),
        ]);

        if ($scheduleId === false) {
            return ['success' => false, 'message' => 'Failed to create schedule. Check the recurrence rule (e.g. end date must be in the future).'];
        }

        // Link the task to the schedule: it becomes the first occurrence, so
        // the schedule will not spawn a duplicate for the current period.
        if (!$taskModel->linkToSchedule($taskId, $scheduleId)) {
            error_log("Failed to link task {$taskId} to schedule {$scheduleId}");
            return ['success' => false, 'message' => 'Schedule created but the task could not be linked.'];
        }

        // The schedule template now owns its own copies of the task's image
        // references (same files on disk) — removing an image from the
        // original task must not break spawned recurring tasks.
        (new ItemImageService($this->db))->copyImageReferences($taskId, 'task', $scheduleId, 'schedule');

        return [
            'success' => true,
            'message' => 'Task is now recurring',
            'schedule_id' => $scheduleId,
        ];
    }

    /**
     * Delete a schedule.
     */
    public function handleDeleteSchedule(int $scheduleId): array
    {
        $schedule = $this->schedules->getScheduleById($scheduleId);
        if (empty($schedule)) {
            return ['success' => false, 'message' => 'Schedule not found.'];
        }

        if (!$this->board->validateBoardWriteAccess($this->user->getUserId(), (int)$schedule[0]['board_id'])) {
            return ['success' => false, 'message' => 'You do not have write access to this board.'];
        }

        // Reference-counted removal of the template's image rows — the files
        // survive while spawned tasks still reference them.
        (new ItemImageService($this->db))->deleteAllForItem(
            (int)$schedule[0]['user_id'],
            $scheduleId,
            'schedule'
        );

        return $this->schedules->deleteSchedule($scheduleId)
            ? ['success' => true, 'message' => 'Recurring task deleted successfully']
            : ['success' => false, 'message' => 'Failed to delete schedule.'];
    }

    /**
     * Toggle a schedule's active state.
     *
     * @return array Includes 'is_active' with the new state on success.
     */
    public function handleToggleSchedule(int $scheduleId): array
    {
        $schedule = $this->schedules->getScheduleById($scheduleId);
        if (empty($schedule)) {
            return ['success' => false, 'message' => 'Schedule not found.'];
        }

        if (!$this->board->validateBoardWriteAccess($this->user->getUserId(), (int)$schedule[0]['board_id'])) {
            return ['success' => false, 'message' => 'You do not have write access to this board.'];
        }

        $newState = $this->schedules->toggleSchedule($scheduleId);
        if ($newState === null) {
            return ['success' => false, 'message' => 'Failed to update schedule.'];
        }

        return [
            'success' => true,
            'message' => $newState ? 'Schedule activated' : 'Schedule paused',
            'is_active' => (bool)$newState,
        ];
    }

    /**
     * Load a schedule for editing (with ownership check).
     *
     * @return array ['success' => bool, 'message' => string, 'schedule' => array]
     */
    public function handleGetSchedule(int $scheduleId): array
    {
        $schedule = $this->schedules->getScheduleById($scheduleId);
        if (empty($schedule)) {
            return ['success' => false, 'message' => 'Schedule not found.', 'schedule' => []];
        }

        if (!$this->board->validateBoardWriteAccess($this->user->getUserId(), (int)$schedule[0]['board_id'])) {
            return ['success' => false, 'message' => 'You do not have write access to this board.', 'schedule' => []];
        }

        return ['success' => true, 'message' => '', 'schedule' => $schedule[0]];
    }

    /**
     * List all schedules for a board (with write-access check).
     *
     * @return array ['success' => bool, 'schedules' => array]
     */
    public function handleGetSchedules(int $boardId): array
    {
        if (!$this->board->validateBoardWriteAccess($this->user->getUserId(), $boardId)) {
            return ['success' => false, 'schedules' => []];
        }

        return ['success' => true, 'schedules' => $this->schedules->getSchedulesForBoard($boardId)];
    }

    /**
     * Process due schedules for a board (lazy execution hook).
     * Never throws — failures are logged and skipped.
     */
    public function runDueSchedules(int $boardId): int
    {
        try {
            return $this->schedules->runDueSchedules($boardId);
        } catch (\Exception $e) {
            error_log("TaskScheduleController::runDueSchedules error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Build a human-readable summary of a recurrence rule,
     * e.g. "Every 2 weeks on Mon, Fri" or "Monthly on the 2nd Tuesday".
     */
    public static function describeRule(array $schedule): string
    {
        $interval = max(1, (int)($schedule['interval'] ?? 1));
        $frequency = $schedule['frequency'] ?? 'daily';

        switch ($frequency) {
            case 'daily':
                return $interval === 1 ? 'Every day' : "Every {$interval} days";

            case 'weekly':
                $days = array_filter(array_map('intval', explode(',', (string)($schedule['weekdays'] ?? ''))));
                $names = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
                $prefix = $interval === 1 ? 'Weekly' : "Every {$interval} weeks";
                if (empty($days)) {
                    return $prefix;
                }
                return $prefix . ' on ' . implode(', ', array_map(fn($d) => $names[$d] ?? '?', $days));

            case 'monthly':
                $prefix = $interval === 1 ? 'Monthly' : "Every {$interval} months";
                if (!empty($schedule['nth_weekday']) && preg_match('/^([1-5]):([0-6])$/', $schedule['nth_weekday'], $m)) {
                    $ordinals = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th'];
                    $names = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
                    return $prefix . ' on the ' . ($ordinals[(int)$m[1]] ?? '?') . ' ' . ($names[(int)$m[2]] ?? '?');
                }
                $day = (int)($schedule['month_day'] ?? 1);
                return $prefix . " on day {$day}";

            case 'yearly':
                $prefix = $interval === 1 ? 'Yearly' : "Every {$interval} years";
                $day = (int)($schedule['month_day'] ?? 1);
                return $prefix . " on day {$day}";

            default:
                return ucfirst($frequency);
        }
    }

    /**
     * Describe the end condition, e.g. "Ends after 5 runs" / "Ends Dec 31, 2026".
     */
    public static function describeEnd(array $schedule): string
    {
        switch ($schedule['end_type'] ?? 'never') {
            case 'count':
                return 'Ends after ' . (int)($schedule['end_count'] ?? 0) . ' runs';
            case 'date':
                $ts = strtotime((string)($schedule['end_date'] ?? ''));
                return $ts ? 'Ends ' . date('M j, Y', $ts) : 'Ends by date';
            default:
                return '';
        }
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * Validate schedule POST data.
     *
     * NOTE: any defaults assigned here (e.g. start_date) apply only to this
     * method's local copy of $postData — callers must set their own defaults
     * before calling.
     *
     * @return array|null Null when valid, otherwise ['success' => false, 'message' => string].
     */
    private function validateScheduleData(array $postData, ?int $boardId = null): ?array
    {
        $title = trim($postData['task_title'] ?? '');
        if (mb_strlen($title) < 2 || mb_strlen($title) > 99) {
            return ['success' => false, 'message' => 'Task title must be between 2 and 99 characters.'];
        }

        if (!in_array($postData['frequency'] ?? '', TaskSchedule::FREQUENCIES, true)) {
            return ['success' => false, 'message' => 'Invalid frequency.'];
        }

        $interval = (int)($postData['interval'] ?? 1);
        if ($interval < 1 || $interval > 99) {
            return ['success' => false, 'message' => 'Interval must be between 1 and 99.'];
        }

        $columnId = (int)($postData['column_id'] ?? 0);
        if ($columnId <= 0) {
            return ['success' => false, 'message' => 'A target column is required.'];
        }

        if ($boardId !== null && $boardId > 0) {
            $colCheck = $this->db->q(
                "SELECT 1 FROM `tm_column` WHERE `id` = ? AND `parent_id` = ? LIMIT 1",
                "ii",
                $columnId,
                $boardId
            );
            if (empty($colCheck)) {
                return ['success' => false, 'message' => 'Selected column does not belong to this board.'];
            }
        }

        // start_date is no longer shown in the dialog — default to today.
        // An explicit value is still honoured (e.g. programmatic/API use).
        if (empty(trim($postData['start_date'] ?? ''))) {
            $postData['start_date'] = date('Y-m-d');
        }

        $startDate = trim($postData['start_date'] ?? '');
        $d = \DateTime::createFromFormat('Y-m-d', $startDate);
        if (!$d || $d->format('Y-m-d') !== $startDate) {
            return ['success' => false, 'message' => 'A valid start date is required.'];
        }

        $endType = $postData['end_type'] ?? 'never';
        if (!in_array($endType, TaskSchedule::END_TYPES, true)) {
            return ['success' => false, 'message' => 'Invalid end condition.'];
        }

        if ($endType === 'count' && (int)($postData['end_count'] ?? 0) < 1) {
            return ['success' => false, 'message' => 'End count must be at least 1.'];
        }

        if ($endType === 'date') {
            $endDate = trim($postData['end_date'] ?? '');
            $e = \DateTime::createFromFormat('Y-m-d', $endDate);
            if (!$e || $e->format('Y-m-d') !== $endDate) {
                return ['success' => false, 'message' => 'A valid end date is required.'];
            }
            if ($endDate < $startDate) {
                return ['success' => false, 'message' => 'End date cannot be before the start date.'];
            }
        }

        if (($postData['frequency'] ?? '') === 'weekly') {
            $weekdays = $this->collectWeekdays($postData);
            if ($weekdays === null || $weekdays === '') {
                return ['success' => false, 'message' => 'Select at least one weekday for a weekly schedule.'];
            }
        }

        return null;
    }

    /**
     * Collect checked weekday checkboxes into a CSV string (or null).
     */
    private function collectWeekdays(array $postData): ?string
    {
        $days = $postData['weekdays'] ?? [];
        if (!is_array($days)) {
            return null;
        }
        $days = array_values(array_filter(array_map('intval', $days), fn($d) => $d >= 0 && $d <= 6));
        sort($days);
        return !empty($days) ? implode(',', $days) : null;
    }
}
