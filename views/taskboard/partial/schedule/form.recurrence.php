<?php
use Dashboard\Core\Sanitize;
use Dashboard\Taskboard\TaskSchedule;

/**
 * Reusable recurrence-rule form fields.
 *
 * Extracted from schedule/dialog.edit.php so both the standalone recurring
 * dialog and the slide-out panel (panel.recurrence.php) render identical
 * schedule settings.
 *
 * Expected variables (set by the including view):
 * @var string  $frequency        daily|weekly|monthly|yearly
 * @var int     $interval
 * @var array   $weekdays         int[] 0-6 (Sun-Sat)
 * @var int     $monthDay
 * @var string  $nthWeekday       e.g. '2:3' or ''
 * @var string  $endType          never|count|date
 * @var int     $endCount
 * @var string  $endDate          Y-m-d
 * @var bool    $onlyIfCompleted
 * @var int     $priority         0-4 (only used when $showPriority is true)
 * @var bool    $showPriority     render the Priority pills? The task slide-out
 *                               panel omits them — the task dialog already has
 *                               its own priority selector. Defaults to true.
 * @var array   $columns          ['success'=>bool,'columns'=>[['id','column_name'],...]]
 * @var int     $columnId         selected column (0 = none)
 * @var string  $idPrefix         unique prefix for input ids (forms on the same page)
 */
$weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$nthNames = ['1st', '2nd', '3rd', '4th', '5th'];
$weekdayFull = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$idPrefix = $idPrefix ?? 'rec';
$showPriority = $showPriority ?? true;
?>
<div class="flex-table">
    <div class="flex-row">
        <div class="flex-cell">
            <label for="<?= $idPrefix ?>-column_id">Add task to column:</label>
            <select name="column_id" id="<?= $idPrefix ?>-column_id" style="width: 100%;">
                <option value="0">pick a column</option>
                <?php if (($columns['success'] ?? false)): ?>
                    <?php foreach ($columns['columns'] as $column): ?>
                        <option value="<?= (int)$column['id'] ?>" <?= $columnId === (int)$column['id'] ? 'selected' : '' ?>>
                            <?= Sanitize::e(html_entity_decode($column['column_name'])) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <div class="flex-row">
        <div class="flex-cell">
            <span class="form-label">Repeats</span>
            <div class="segmented segmented-frequency" role="radiogroup" aria-label="Repeats">
                <?php foreach (TaskSchedule::FREQUENCIES as $freq): ?>
                    <input type="radio" id="<?= $idPrefix ?>-freq-<?= $freq ?>" name="frequency" value="<?= $freq ?>"
                        <?= $frequency === $freq ? 'checked' : '' ?>>
                    <label for="<?= $idPrefix ?>-freq-<?= $freq ?>"><?= ucfirst($freq) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Weekly: weekday segmented control -->
    <div class="flex-row schedule-options" data-show-for="weekly" <?= $frequency === 'weekly' ? '' : 'style="display:none;"' ?>>
        <div class="flex-cell">
            <div class="segmented segmented-weekdays" role="group" aria-label="Repeat on">
                <?php foreach ($weekdayNames as $i => $dayName): ?>
                    <input type="checkbox" id="<?= $idPrefix ?>-weekday-<?= $i ?>" name="weekdays[]" value="<?= $i ?>"
                        <?= in_array($i, $weekdays, true) ? 'checked' : '' ?>>
                    <label for="<?= $idPrefix ?>-weekday-<?= $i ?>"><?= $dayName ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Monthly: day-of-month OR nth-weekday -->
    <div class="flex-row schedule-options" data-show-for="monthly" <?= $frequency === 'monthly' ? '' : 'style="display:none;"' ?>>
        <div class="flex-cell">
            <span class="form-label">Monthly on</span>
            <select name="month_day" id="<?= $idPrefix ?>-month_day" <?= $nthWeekday ? 'disabled' : '' ?>>
                <?php for ($d = 1; $d <= 31; $d++): ?>
                    <option value="<?= $d ?>" <?= !$nthWeekday && $monthDay === $d ? 'selected' : '' ?>>day <?= $d ?></option>
                <?php endfor; ?>
            </select>
            <select name="nth_weekday" id="<?= $idPrefix ?>-nth_weekday" class="schedule-nth-select" <?= !$nthWeekday ? 'disabled' : '' ?>>
                <option value="">pick weekday…</option>
                <?php foreach ($nthNames as $ni => $nthName): ?>
                    <?php foreach ($weekdayFull as $wi => $wdFull): ?>
                        <option value="<?= ($ni + 1) ?>:<?= $wi ?>" <?= $nthWeekday === ($ni + 1) . ':' . $wi ? 'selected' : '' ?>>
                            <?= $nthName . ' ' . $wdFull ?>
                        </option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
            <span class="form-label schedule-monthly-hint">Choose “day N” for a fixed date, or a weekday for e.g. “2nd Tuesday”. The other picker is disabled.</span>
        </div>
    </div>

    <!-- Yearly: day-of-month -->
    <div class="flex-row schedule-options" data-show-for="yearly" <?= $frequency === 'yearly' ? '' : 'style="display:none;"' ?>>
        <div class="flex-cell">
            <label for="<?= $idPrefix ?>-yearly_month_day"><span class="form-label">Yearly on day (of the start-date month)</span></label>
            <input type="number" name="month_day_yearly" id="<?= $idPrefix ?>-yearly_month_day" min="1" max="31" value="<?= $monthDay ?>">
        </div>
    </div>

    <div class="flex-row">
        <div class="flex-cell">
            <label for="<?= $idPrefix ?>-interval"><span class="form-label">Every</span></label>
            <div class="schedule-interval-row">
                <input type="number" name="interval" id="<?= $idPrefix ?>-interval" min="1" max="99" value="<?= $interval ?>">
                <span class="schedule-interval-unit" data-unit-for="interval">day(s)</span>
            </div>
        </div>
    </div>

    <div class="flex-row">
        <div class="flex-cell schedule-completed-row">
            <input type="checkbox" id="<?= $idPrefix ?>-only_if_completed" name="only_if_completed" value="1"
                <?= !empty($onlyIfCompleted) ? 'checked' : '' ?>>
            <label for="<?= $idPrefix ?>-only_if_completed">Only if last task was completed</label>
        </div>
    </div>

    <div class="flex-row">
        <div class="flex-cell">
            <span class="form-label">Stop creating new copies</span>
            <div class="schedule-end-list">
                <div class="schedule-end-option">
                    <input type="radio" id="<?= $idPrefix ?>-end-never" name="end_type" value="never" <?= $endType === 'never' ? 'checked' : '' ?>>
                    <label for="<?= $idPrefix ?>-end-never">Never</label>
                </div>
                <div class="schedule-end-option">
                    <input type="radio" id="<?= $idPrefix ?>-end-count" name="end_type" value="count" <?= $endType === 'count' ? 'checked' : '' ?>>
                    <label for="<?= $idPrefix ?>-end-count">After</label>
                    <span class="schedule-end-input" data-show-end="count" <?= $endType === 'count' ? '' : 'style="display:none;"' ?>>
                        <input type="number" name="end_count" id="<?= $idPrefix ?>-end_count" min="1" max="9999" value="<?= $endCount ?>">
                        <span class="schedule-interval-unit">times</span>
                    </span>
                </div>
                <div class="schedule-end-option">
                    <input type="radio" id="<?= $idPrefix ?>-end-date" name="end_type" value="date" <?= $endType === 'date' ? 'checked' : '' ?>>
                    <label for="<?= $idPrefix ?>-end-date">On</label>
                    <span class="schedule-end-input" data-show-end="date" <?= $endType === 'date' ? '' : 'style="display:none;"' ?>>
                        <input type="date" name="end_date" id="<?= $idPrefix ?>-end_date" value="<?= Sanitize::e($endDate) ?>">
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($showPriority): ?>
    <div class="flex-row">
        <div class="flex-cell">
            <span class="form-label">Priority</span>
            <!-- Same markup as the task dialog: default .pill-select layout,
                 colored via .pill-select-label.priority-* in task.css -->
            <div class="pill-select">
                <?php foreach ([0 => ['Lowest', 'priority-lowest'], 1 => ['Low', 'priority-low'], 2 => ['Alarming', 'priority-alarming'], 3 => ['Critical', 'priority-critical'], 4 => ['Highest', 'priority-highest']] as $pVal => [$pName, $pClass]): ?>
                    <input type="radio" id="<?= $idPrefix ?>-priority-<?= $pVal ?>" name="task_priority" value="<?= $pVal ?>"
                        <?= $priority === $pVal ? 'checked' : '' ?>>
                    <label for="<?= $idPrefix ?>-priority-<?= $pVal ?>" class="pill-select-label <?= $pClass ?>"><?= $pName ?></label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
    // Toggle conditional recurrence option rows based on selected frequency.
    // Scoped to this partial's form so multiple recurrence forms can coexist
    // on one page (standalone dialog + slide-out panel).
    (function () {
        var form = document.getElementById('<?= $idPrefix ?>-form');
        if (!form) return;

        function syncOptions() {
            var freq = form.querySelector('input[name="frequency"]:checked');
            var freqValue = freq ? freq.value : 'daily';

            form.querySelectorAll('.schedule-options').forEach(function (row) {
                row.style.display = row.getAttribute('data-show-for') === freqValue ? '' : 'none';
            });

            // Interval unit label follows the frequency
            var unit = form.querySelector('.schedule-interval-unit');
            if (unit) unit.textContent = freqValue === 'daily' ? 'day(s)'
                : freqValue === 'weekly' ? 'week(s)'
                : freqValue === 'monthly' ? 'month(s)' : 'year(s)';

            // Enable/disable end-condition inputs
            var endType = form.querySelector('input[name="end_type"]:checked');
            var endValue = endType ? endType.value : 'never';
            form.querySelectorAll('.schedule-end-input').forEach(function (row) {
                row.style.display = row.getAttribute('data-show-end') === endValue ? '' : 'none';
            });

            // Monthly: choosing nth-weekday disables the plain day select
            var monthDay = form.querySelector('#<?= $idPrefix ?>-month_day');
            var nth = form.querySelector('#<?= $idPrefix ?>-nth_weekday');
            if (monthDay && nth) {
                monthDay.disabled = nth.value !== '';
                nth.disabled = false;
            }
        }

        form.addEventListener('change', syncOptions);

        // Clicking/focusing an inline input in the "Stop creating new copies"
        // list selects that option's radio.
        form.addEventListener('focusin', function (e) {
            var option = e.target.closest('.schedule-end-option');
            if (option && e.target.tagName === 'INPUT' && e.target.type !== 'radio') {
                var radio = option.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    syncOptions();
                }
            }
        });

        syncOptions();
    })();
</script>