<?php
namespace Dashboard\Taskboard;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\ItemImageService;

/**
 * Model for recurring task schedules (tm_task_schedule).
 *
 * A schedule is a task template plus a recurrence rule. When the schedule's
 * `next_run` time has passed, a fresh task is spawned into the target column
 * (see runDueSchedules()). Execution is lazy: due schedules are processed
 * whenever the board is loaded/refreshed — no cron required.
 */
class TaskSchedule
{
    /** @var string[] Valid frequency values */
    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'yearly'];

    /** @var string[] Valid end-type values */
    public const END_TYPES = ['never', 'count', 'date'];

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------
    // CRUD
    // ------------------------------------------------------------------

    /**
     * Create a new schedule.
     *
     * @param array $data Column => value map matching tm_task_schedule fields.
     *                    Required: board_id, user_id, column_id, task_title,
     *                    frequency, start_date. Optional fields default sensibly.
     * @return int|false New schedule ID or false on failure.
     */
    public function addSchedule(array $data): int|false
    {
        $title = trim($data['task_title'] ?? '');
        if ($title === '' || mb_strlen($title) > 100) {
            return false;
        }

        $frequency = $data['frequency'] ?? 'daily';
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            return false;
        }

        $startDate = $this->normalizeDate($data['start_date'] ?? '');
        if ($startDate === null) {
            return false;
        }

        $interval = max(1, (int)($data['interval'] ?? 1));
        $endType = in_array($data['end_type'] ?? 'never', self::END_TYPES, true)
            ? $data['end_type'] : 'never';

        $nextRun = isset($data['next_run'])
            ? $data['next_run']
            : $this->calculateFirstRun(
                $frequency,
                $interval,
                $startDate,
                $data['weekdays'] ?? null,
                $data['month_day'] ?? null,
                $data['nth_weekday'] ?? null,
                $endType,
                (int)($data['end_count'] ?? 0),
                $this->normalizeDate($data['end_date'] ?? '') ?? '9999-12-31'
            );

        // Rule can never fire (e.g. end date in the past) — reject.
        if ($nextRun === null) {
            return false;
        }

        $runCount = max(0, (int)($data['run_count'] ?? 0));
        $lastRun = !empty($data['last_run']) ? $data['last_run'] : ($runCount > 0 ? date('Y-m-d H:i:s') : null);

        $sql = "INSERT INTO `tm_task_schedule`
                (`board_id`, `user_id`, `column_id`, `task_title`, `task_desc`,
                 `task_checklist`, `task_priority`, `labels`, `frequency`, `interval`,
                 `weekdays`, `month_day`, `nth_weekday`, `start_date`, `end_type`,
                 `end_count`, `only_if_completed`, `end_date`, `next_run`, `last_run`, `run_count`, `is_active`, `created`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";

        // 21 placeholders: iii sss i ss i sssss iisssi
        $result = $this->db->q(
            $sql,
            "iiisssississsssiisssi",
            (int)$data['board_id'],
            (int)$data['user_id'],
            (int)$data['column_id'],
            $title,
            $data['task_desc'] ?? '',
            $this->normalizeChecklist($data['task_checklist'] ?? null),
            (int)($data['task_priority'] ?? 0),
            $this->normalizeLabels($data['labels'] ?? null),
            $frequency,
            $interval,
            $data['weekdays'] ?? null,
            $data['month_day'] ?? null,
            $data['nth_weekday'] ?? null,
            $startDate,
            $endType,
            $endType === 'count' ? max(1, (int)($data['end_count'] ?? 1)) : null,
            !empty($data['only_if_completed']) ? 1 : 0,
            $endType === 'date' ? $this->normalizeDate($data['end_date'] ?? '') : null,
            $nextRun,
            $lastRun,
            $runCount
        );

        return $result !== false ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Update an existing schedule. Recomputes next_run from the new rule.
     *
     * @return bool
     */
    public function updateSchedule(int $scheduleId, array $data): bool
    {
        $existing = $this->getScheduleById($scheduleId);
        if (empty($existing)) {
            return false;
        }
        $existing = $existing[0];

        $title = trim($data['task_title'] ?? $existing['task_title']);
        if ($title === '' || mb_strlen($title) > 100) {
            return false;
        }

        $frequency = $data['frequency'] ?? $existing['frequency'];
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            return false;
        }

        $startDate = $this->normalizeDate($data['start_date'] ?? $existing['start_date']);
        if ($startDate === null) {
            return false;
        }

        $interval = max(1, (int)($data['interval'] ?? $existing['interval']));
        $endType = in_array($data['end_type'] ?? $existing['end_type'], self::END_TYPES, true)
            ? ($data['end_type'] ?? $existing['end_type']) : 'never';

        $endCount = $endType === 'count' ? max(1, (int)($data['end_count'] ?? $existing['end_count'] ?? 1)) : null;
        $endDate = $endType === 'date' ? $this->normalizeDate($data['end_date'] ?? $existing['end_date']) : null;

        $existingRunCount = (int)$existing['run_count'];
        $after = !empty($existing['last_run'])
            ? $existing['last_run']
            : ($existingRunCount > 0 ? date('Y-m-d H:i:s') : null);

        if ($after !== null) {
            $nextRun = $this->calculateNextRun(
                $frequency,
                $interval,
                $startDate,
                $data['weekdays'] ?? $existing['weekdays'],
                $data['month_day'] ?? $existing['month_day'],
                $data['nth_weekday'] ?? $existing['nth_weekday'],
                $endType,
                (int)($endCount ?? 0),
                $endDate ?? '9999-12-31',
                $existingRunCount,
                $after
            );
        } else {
            $nextRun = $this->calculateFirstRun(
                $frequency,
                $interval,
                $startDate,
                $data['weekdays'] ?? $existing['weekdays'],
                $data['month_day'] ?? $existing['month_day'],
                $data['nth_weekday'] ?? $existing['nth_weekday'],
                $endType,
                (int)($endCount ?? 0),
                $endDate ?? '9999-12-31'
            );
        }

        // If the rule can never fire again, deactivate instead of failing hard.
        $isActive = $nextRun !== null ? (int)$existing['is_active'] : 0;
        if ($nextRun === null) {
            $nextRun = $existing['next_run'];
        }

        $sql = "UPDATE `tm_task_schedule` SET
                    `column_id` = ?, `task_title` = ?, `task_desc` = ?, `task_checklist` = ?,
                    `task_priority` = ?, `labels` = ?, `frequency` = ?, `interval` = ?,
                    `weekdays` = ?, `month_day` = ?, `nth_weekday` = ?, `start_date` = ?,
                    `end_type` = ?, `end_count` = ?, `only_if_completed` = ?, `end_date` = ?,
                    `next_run` = ?, `is_active` = ?, `modified` = NOW()
                WHERE `schedule_id` = ? LIMIT 1";

        // 19 placeholders: i sss i ss i sssss ii ss ii
        $result = $this->db->q(
            $sql,
            "isssississsssiissii",
            (int)($data['column_id'] ?? $existing['column_id']),
            $title,
            $data['task_desc'] ?? $existing['task_desc'],
            $this->normalizeChecklist($data['task_checklist'] ?? $existing['task_checklist']),
            (int)($data['task_priority'] ?? $existing['task_priority']),
            $this->normalizeLabels($data['labels'] ?? $existing['labels']),
            $frequency,
            $interval,
            $data['weekdays'] ?? $existing['weekdays'],
            $data['month_day'] ?? $existing['month_day'],
            $data['nth_weekday'] ?? $existing['nth_weekday'],
            $startDate,
            $endType,
            $endCount,
            !empty($data['only_if_completed']) ? 1 : 0,
            $endDate,
            $nextRun,
            $isActive,
            $scheduleId
        );

        return $result !== false;
    }

    /**
     * Delete a schedule permanently.
     *
     * Also unlinks any tasks pointing at it (tm_task.schedule_id), otherwise
     * those tasks would keep referencing a non-existent schedule and later
     * saves from the recurrence panel would fail with "Schedule not found".
     */
    public function deleteSchedule(int $scheduleId): bool
    {
        $this->db->q(
            "UPDATE `tm_task` SET `schedule_id` = NULL WHERE `schedule_id` = ?",
            "i",
            $scheduleId
        );

        $result = $this->db->q(
            "DELETE FROM `tm_task_schedule` WHERE `schedule_id` = ? LIMIT 1",
            "i",
            $scheduleId
        );
        return $result !== false;
    }

    /**
     * Toggle (or explicitly set) the active flag of a schedule.
     *
     * @return bool|null New active state, null on failure. Uses all three
     *                   states because "paused" (false) is a valid success result.
     */
    public function toggleSchedule(int $scheduleId, ?bool $setActive = null): ?bool
    {
        $schedule = $this->getScheduleById($scheduleId);
        if (empty($schedule)) {
            return null;
        }

        $newState = $setActive ?? !(bool)$schedule[0]['is_active'];
        $result = $this->db->q(
            "UPDATE `tm_task_schedule` SET `is_active` = ?, `modified` = NOW() WHERE `schedule_id` = ? LIMIT 1",
            "ii",
            (int)$newState,
            $scheduleId
        );

        return $result !== false ? $newState : null;
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Fetch a single schedule by ID.
     */
    public function getScheduleById(int $scheduleId): array
    {
        $result = $this->db->q(
            "SELECT * FROM `tm_task_schedule` WHERE `schedule_id` = ? LIMIT 1",
            "i",
            $scheduleId
        );
        return $result ?: [];
    }

    /**
     * Fetch all schedules for a board (any state), newest first.
     */
    public function getSchedulesForBoard(int $boardId): array
    {
        $result = $this->db->q(
            "SELECT * FROM `tm_task_schedule` WHERE `board_id` = ? ORDER BY `is_active` DESC, `next_run` ASC",
            "i",
            $boardId
        );
        return $result ?: [];
    }

    /**
     * Fetch active schedules whose next_run has passed.
     *
     * @return array[] Rows due for spawning.
     */
    public function getDueSchedules(int $boardId): array
    {
        $result = $this->db->q(
            "SELECT * FROM `tm_task_schedule`
             WHERE `board_id` = ? AND `is_active` = 1 AND `next_run` <= NOW()
             ORDER BY `next_run` ASC",
            "i",
            $boardId
        );
        return $result ?: [];
    }

    // ------------------------------------------------------------------
    // Execution
    // ------------------------------------------------------------------

    /**
     * Spawn tasks for all due schedules on a board and advance their next_run.
     *
     * Catch-up behaviour: if a schedule missed multiple occurrences (nobody
     * loaded the board for weeks), only ONE task is spawned and next_run is
     * fast-forwarded past all missed occurrences.
     *
     * @return int Number of tasks spawned.
     */
    public function runDueSchedules(int $boardId): int
    {
        $due = $this->getDueSchedules($boardId);
        if (empty($due)) {
            return 0;
        }

        $spawned = 0;
        foreach ($due as $schedule) {
            $this->db->beginTransaction();
            try {
                // Completion gate: only spawn when the previous spawned task
                // was resolved (moved into a resolved-flag column).
                if (!empty($schedule['only_if_completed'])
                    && !$this->wasLastTaskResolved((int)$schedule['schedule_id'])
                ) {
                    // Skip spawning this cycle, but still advance next_run.
                    $this->advanceSchedule($schedule);
                    $this->db->commit();
                    continue;
                }

                $taskId = $this->spawnTaskFromSchedule($schedule);
                if ($taskId === false) {
                    $this->db->rollback();
                    continue;
                }

                $this->advanceSchedule($schedule);

                $this->db->commit();
                $spawned++;
            } catch (\Exception $e) {
                $this->db->rollback();
                error_log("TaskSchedule::runDueSchedules failed for schedule {$schedule['schedule_id']}: " . $e->getMessage());
            }
        }

        return $spawned;
    }

    /**
     * Insert a task from a schedule template, including its labels.
     *
     * @return int|false New task ID or false on failure.
     */
    private function spawnTaskFromSchedule(array $schedule): int|false
    {
        $sql = "INSERT INTO `tm_task`
                (`board_id`, `column_id`, `task_title`, `task_desc`, `task_checklist`,
                 `task_priority`, `schedule_id`, `task_created`, `task_modified`)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $result = $this->db->q(
            $sql,
            "iissiii",
            (int)$schedule['board_id'],
            (int)$schedule['column_id'],
            $schedule['task_title'],
            $schedule['task_desc'],
            $schedule['task_checklist'],
            (int)$schedule['task_priority'],
            (int)$schedule['schedule_id']
        );

        if ($result === false) {
            return false;
        }

        $taskId = (int)$this->db->lastInsertId();

        $labelIds = json_decode((string)($schedule['labels'] ?? '[]'), true);
        if (is_array($labelIds) && !empty($labelIds)) {
            $relSql = "INSERT IGNORE INTO `tm_task_label_rel` (`task_id`, `label_id`) VALUES (?, ?)";
            foreach ($labelIds as $labelId) {
                $labelId = (int)$labelId;
                if ($labelId > 0) {
                    $this->db->q($relSql, "ii", $taskId, $labelId);
                }
            }
        }

        // The spawned task gets its own image references pointing at the
        // template's files, so deleting the template (or any other copy)
        // cannot pull the files out from under this task.
        (new ItemImageService($this->db))->copyImageReferences(
            (int)$schedule['schedule_id'],
            'schedule',
            $taskId,
            'task'
        );

        // Attachments are copied as independent files + rows owned by the
        // spawned task (schedule templates don't hold attachments directly;
        // they inherit from the task that was made recurring).
        (new \Dashboard\Core\AttachmentService($this->db))->copyAttachments(
            (int)$schedule['user_id'],
            (int)$schedule['schedule_id'],
            'schedule',
            $taskId,
            'task'
        );

        return $taskId;
    }

    /**
     * Check whether the most recent task spawned by a schedule was resolved
     * (i.e. moved into a column with the resolved flag, which sets
     * task_resolved_date). A deleted task counts as NOT completed.
     *
     * @param int $scheduleId
     * @return bool True if the last spawned task exists and is resolved.
     */
    private function wasLastTaskResolved(int $scheduleId): bool
    {
        $result = $this->db->q(
            "SELECT `task_resolved_date` FROM `tm_task`
             WHERE `schedule_id` = ?
             ORDER BY `task_id` DESC
             LIMIT 1",
            "i",
            $scheduleId
        );

        if (empty($result)) {
            return true; // No active previous task exists (first run or previous deleted) — allow spawning.
        }

        return $result[0]['task_resolved_date'] !== null;
    }

    /**
     * Advance a schedule after a spawn: bump run_count, set last_run,
     * compute the next occurrence, and deactivate when the rule is exhausted.
     */
    private function advanceSchedule(array $schedule): void
    {
        $newRunCount = (int)$schedule['run_count'] + 1;

        $nextRun = $this->calculateNextRun(
            $schedule['frequency'],
            (int)$schedule['interval'],
            $schedule['start_date'],
            $schedule['weekdays'],
            $schedule['month_day'],
            $schedule['nth_weekday'],
            $schedule['end_type'],
            (int)($schedule['end_count'] ?? 0),
            $schedule['end_date'] ?? '9999-12-31',
            $newRunCount,
            $schedule['next_run']
        );

        if ($nextRun === null) {
            // Rule exhausted (count reached or end date passed) — deactivate.
            $this->db->q(
                "UPDATE `tm_task_schedule`
                 SET `run_count` = ?, `last_run` = NOW(), `next_run` = NULL,
                     `is_active` = 0, `modified` = NOW()
                 WHERE `schedule_id` = ? LIMIT 1",
                "ii",
                $newRunCount,
                (int)$schedule['schedule_id']
            );
        } else {
            $this->db->q(
                "UPDATE `tm_task_schedule`
                 SET `run_count` = ?, `last_run` = NOW(), `next_run` = ?, `modified` = NOW()
                 WHERE `schedule_id` = ? LIMIT 1",
                "isi",
                $newRunCount,
                $nextRun,
                (int)$schedule['schedule_id']
            );
        }
    }

    // ------------------------------------------------------------------
    // Recurrence calculation
    // ------------------------------------------------------------------

    /**
     * Calculate the first occurrence datetime for a recurrence rule on or after start date.
     *
     * @return string|null Y-m-d H:i:s or null when rule can never fire.
     */
    public function calculateFirstRun(
        string $frequency,
        int $interval,
        string $startDate,
        ?string $weekdays,
        ?string $monthDay,
        ?string $nthWeekday,
        string $endType,
        int $endCount,
        string $endDate
    ): ?string {
        if ($endType === 'count' && $endCount < 1) {
            return null;
        }

        $start = \DateTime::createFromFormat('Y-m-d', substr($startDate, 0, 10));
        if (!$start) {
            return null;
        }
        $start->setTime(0, 0, 0);

        // If the start date itself matches the recurrence rule, that is the first occurrence.
        if ($this->matchesRecurrenceRule($start, $frequency, $weekdays, $monthDay, $nthWeekday)) {
            if ($endType === 'date' && $start->format('Y-m-d') > substr($endDate, 0, 10)) {
                return null;
            }
            return $start->format('Y-m-d H:i:s');
        }

        // Otherwise find the earliest occurrence strictly after start date.
        return $this->calculateNextRun(
            $frequency,
            $interval,
            $startDate,
            $weekdays,
            $monthDay,
            $nthWeekday,
            $endType,
            $endCount,
            $endDate,
            0,
            $startDate
        );
    }

    /**
     * Check whether a specific date matches the recurrence rule filters.
     */
    public function matchesRecurrenceRule(
        \DateTime $date,
        string $frequency,
        ?string $weekdays,
        ?string $monthDay,
        ?string $nthWeekday
    ): bool {
        switch ($frequency) {
            case 'daily':
                return true;

            case 'weekly':
                $days = $this->parseWeekdays($weekdays);
                if (empty($days)) {
                    return true;
                }
                return in_array((int)$date->format('w'), $days, true);

            case 'monthly':
                if ($nthWeekday !== null && preg_match('/^([1-5]):([0-6])$/', $nthWeekday, $m)) {
                    $expected = $this->nthWeekdayOfMonth((int)$date->format('Y'), (int)$date->format('n'), (int)$m[1], (int)$m[2]);
                    return $expected !== null && $expected->format('Y-m-d') === $date->format('Y-m-d');
                }
                $day = ($monthDay !== null && (int)$monthDay >= 1) ? (int)$monthDay : 1;
                $daysInMonth = (int)(new \DateTime($date->format('Y-m-01')))->format('t');
                $clampedDay = min($day, $daysInMonth);
                return (int)$date->format('j') === $clampedDay;

            case 'yearly':
                $day = ($monthDay !== null && (int)$monthDay >= 1) ? (int)$monthDay : 1;
                $daysInMonth = (int)(new \DateTime($date->format('Y-m-01')))->format('t');
                $clampedDay = min($day, $daysInMonth);
                return (int)$date->format('j') === $clampedDay;

            default:
                return false;
        }
    }

    /**
     * Calculate the next occurrence datetime for a recurrence rule.
     *
     * Pure function — no DB access. All date arithmetic is server-local.
     *
     * @param string    $frequency  daily|weekly|monthly|yearly
     * @param int       $interval   Every N periods (>= 1)
     * @param string    $startDate  Y-m-d anchor date
     * @param ?string   $weekdays   CSV of weekday numbers 0=Sun..6=Sat (weekly)
     * @param ?string   $monthDay   Day of month 1-31 (monthly/yearly)
     * @param ?string   $nthWeekday "nth:weekday" e.g. "2:2" = 2nd Tuesday (monthly)
     * @param string    $endType    never|count|date
     * @param int       $endCount   Max runs (endType=count)
     * @param string    $endDate    Y-m-d last allowed date (endType=date)
     * @param int       $runCount   Runs already performed
     * @param string    $after      Y-m-d (or datetime) to search after
     * @return string|null Y-m-d H:i:s of next occurrence, or null when ended/invalid.
     */
    public function calculateNextRun(
        string $frequency,
        int $interval,
        string $startDate,
        ?string $weekdays,
        ?string $monthDay,
        ?string $nthWeekday,
        string $endType,
        int $endCount,
        string $endDate,
        int $runCount,
        string $after
    ): ?string {
        if ($interval < 1) {
            $interval = 1;
        }

        // End conditions that can be checked up front.
        if ($endType === 'count' && $runCount >= $endCount) {
            return null;
        }

        $start = \DateTime::createFromFormat('Y-m-d', substr($startDate, 0, 10));
        $afterDate = \DateTime::createFromFormat('Y-m-d H:i:s', $after)
            ?: \DateTime::createFromFormat('Y-m-d', substr($after, 0, 10));
        if (!$start || !$afterDate) {
            return null;
        }
        $start->setTime(0, 0, 0);
        $afterDate->setTime(0, 0, 0);

        // Candidate search always starts from the anchor (start date), stepping
        // forward until we pass $after. This makes catch-up deterministic.
        $candidate = clone $start;
        $maxIterations = 5000; // hard stop against pathological rules

        for ($i = 0; $i < $maxIterations; $i++) {
            $next = $this->nextCandidate($candidate, $frequency, $interval, $weekdays, $monthDay, $nthWeekday);
            if ($next === null) {
                return null;
            }
            $candidate = $next;

            if ($candidate > $afterDate) {
                break;
            }
        }

        if ($candidate <= $afterDate) {
            return null; // search space exhausted without passing $after
        }

        // End-date check.
        if ($endType === 'date' && $candidate->format('Y-m-d') > substr($endDate, 0, 10)) {
            return null;
        }

        return $candidate->format('Y-m-d H:i:s');
    }

    /**
     * Advance a candidate date to the next occurrence of the rule, strictly
     * after the given candidate. Invalid dates (e.g. Feb 30) are skipped to
     * the next month that has the requested day.
     *
     * @return \DateTime|null Next candidate, or null when the rule is malformed.
     */
    private function nextCandidate(
        \DateTime $candidate,
        string $frequency,
        int $interval,
        ?string $weekdays,
        ?string $monthDay,
        ?string $nthWeekday
    ): ?\DateTime {
        switch ($frequency) {
            case 'daily':
                $next = clone $candidate;
                $next->modify("+{$interval} days");
                return $next;

            case 'weekly':
                $days = $this->parseWeekdays($weekdays);
                if (empty($days)) {
                    // No weekdays selected: fall back to the anchor weekday.
                    $days = [(int)$candidate->format('w')];
                }

                // Find the earliest matching weekday strictly after candidate.
                $next = clone $candidate;
                for ($i = 1; $i <= 7 * $interval; $i++) {
                    $next->modify('+1 day');
                    if (in_array((int)$next->format('w'), $days, true)) {
                        // Respect the interval: only accept if the week offset
                        // from the candidate's week matches the interval.
                        $weekDiff = $this->weeksBetween($candidate, $next);
                        if ($weekDiff % $interval === 0) {
                            return $next;
                        }
                    }
                }
                return $next; // unreachable in practice

            case 'monthly':
                if ($nthWeekday !== null && preg_match('/^([1-5]):([0-6])$/', $nthWeekday, $m)) {
                    return $this->nextNthWeekday($candidate, $interval, (int)$m[1], (int)$m[2]);
                }
                $day = ($monthDay !== null && (int)$monthDay >= 1) ? (int)$monthDay : (int)$candidate->format('j');
                return $this->nextMonthDay($candidate, $interval, $day);

            case 'yearly':
                $day = ($monthDay !== null && (int)$monthDay >= 1) ? (int)$monthDay : (int)$candidate->format('j');
                $month = (int)$candidate->format('n');
                return $this->nextYearly($candidate, $interval, $month, $day);

            default:
                return null;
        }
    }

    /**
     * Next "nth <weekday>" of a month, stepping by $interval months.
     * Skips months where the nth weekday doesn't exist (e.g. 5th Friday).
     */
    private function nextNthWeekday(\DateTime $candidate, int $interval, int $nth, int $weekday): ?\DateTime
    {
        $year = (int)$candidate->format('Y');
        $month = (int)$candidate->format('n');

        for ($i = 0; $i < 120; $i++) { // up to 10 years of months
            $date = $this->nthWeekdayOfMonth($year, $month, $nth, $weekday);
            if ($date !== null && $date > $candidate) {
                return $date;
            }
            $month += $interval;
            while ($month > 12) {
                $month -= 12;
                $year++;
            }
        }
        return null;
    }

    /**
     * Next occurrence of a day-of-month, stepping by $interval months.
     * Days exceeding a month's length (e.g. day 31 in April or Feb) are
     * clamped to the last day of that month so no months are skipped.
     */
    private function nextMonthDay(\DateTime $candidate, int $interval, int $day): ?\DateTime
    {
        $year = (int)$candidate->format('Y');
        $month = (int)$candidate->format('n');

        for ($i = 0; $i < 120; $i++) {
            $daysInMonth = (int)(new \DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('t');
            $clampedDay = min($day, $daysInMonth);
            $date = \DateTime::createFromFormat('Y-n-j H:i:s', sprintf('%d-%d-%d 00:00:00', $year, $month, $clampedDay));
            if ($date && $date > $candidate) {
                return $date;
            }
            $month += $interval;
            while ($month > 12) {
                $month -= 12;
                $year++;
            }
        }
        return null;
    }

    /**
     * Next yearly occurrence of month/day (month taken from the candidate's
     * anchor month), stepping by $interval years. Clamps to month's last day
     * (e.g. Feb 29 on non-leap years).
     */
    private function nextYearly(\DateTime $candidate, int $interval, int $month, int $day): ?\DateTime
    {
        $year = (int)$candidate->format('Y');

        for ($i = 0; $i < 100; $i++) {
            $daysInMonth = (int)(new \DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('t');
            $clampedDay = min($day, $daysInMonth);
            $date = \DateTime::createFromFormat('Y-n-j H:i:s', sprintf('%d-%d-%d 00:00:00', $year, $month, $clampedDay));
            if ($date && $date > $candidate) {
                return $date;
            }
            $year += $interval;
        }
        return null;
    }

    /**
     * Compute the date of the nth given weekday within a month.
     *
     * @param int $nth     1-5
     * @param int $weekday 0=Sun..6=Sat
     * @return \DateTime|null Null when the nth weekday doesn't exist that month.
     */
    private function nthWeekdayOfMonth(int $year, int $month, int $nth, int $weekday): ?\DateTime
    {
        $first = \DateTime::createFromFormat('Y-n-j H:i:s', sprintf('%d-%d-1 00:00:00', $year, $month));
        if (!$first) {
            return null;
        }

        $offset = ($weekday - (int)$first->format('w') + 7) % 7;
        $day = 1 + $offset + ($nth - 1) * 7;

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return \DateTime::createFromFormat('Y-n-j H:i:s', sprintf('%d-%d-%d 00:00:00', $year, $month, $day));
    }

    /**
     * Parse a CSV weekday string into a sorted list of ints (0-6).
     *
     * @return int[]
     */
    private function parseWeekdays(?string $weekdays): array
    {
        if ($weekdays === null || trim($weekdays) === '') {
            return [];
        }
        $days = array_filter(
            array_map('intval', explode(',', $weekdays)),
            fn($d) => $d >= 0 && $d <= 6
        );
        return array_values(array_unique($days));
    }

    /**
     * Whole-week distance between two dates (candidate <= next).
     */
    private function weeksBetween(\DateTime $from, \DateTime $to): int
    {
        $fromWeek = (clone $from)->modify('monday this week')->setTime(0, 0, 0);
        $toWeek = (clone $to)->modify('monday this week')->setTime(0, 0, 0);
        return (int)floor($fromWeek->diff($toWeek)->days / 7);
    }

    // ------------------------------------------------------------------
    // Normalization helpers
    // ------------------------------------------------------------------

    /**
     * Validate/normalize a Y-m-d date string. Returns null when invalid.
     */
    private function normalizeDate(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', substr(trim($date), 0, 10));
        if (!$d || $d->format('Y-m-d') !== substr(trim($date), 0, 10)) {
            return null;
        }
        return $d->format('Y-m-d');
    }

    /**
     * Normalize checklist input to a JSON string of
     * [['description' => ..., 'status' => 'complete'|'incomplete'], ...] or null.
     *
     * Accepts a JSON string or an array of rows (as posted by the checklist
     * UI). Rows with an empty description are dropped; a missing/unchecked
     * status defaults to 'incomplete' so every stored item always has both
     * keys (the task dialog checklist partial reads them unguarded).
     */
    private function normalizeChecklist(mixed $checklist): ?string
    {
        if (is_string($checklist)) {
            $decoded = json_decode($checklist, true);
            $checklist = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($checklist) || empty($checklist)) {
            return null;
        }

        $items = [];
        foreach ($checklist as $item) {
            if (!is_array($item)) {
                continue;
            }
            $description = trim((string)($item['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $items[] = [
                'description' => $description,
                'status'      => (($item['status'] ?? '') === 'complete') ? 'complete' : 'incomplete',
            ];
        }

        return !empty($items) ? json_encode(array_values($items)) : null;
    }

    /**
     * Normalize labels input to a JSON array string of ints, or null.
     */
    private function normalizeLabels(mixed $labels): ?string
    {
        $ids = [];
        if (is_string($labels)) {
            $decoded = json_decode($labels, true);
            $ids = is_array($decoded) ? $decoded : [];
        } elseif (is_array($labels)) {
            $ids = $labels;
        }

        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        return !empty($ids) ? json_encode($ids) : null;
    }
}
