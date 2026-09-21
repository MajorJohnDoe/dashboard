<?php
use Dashboard\Core\Sanitize;

/**
 * Shared checklist editor — the single source of markup for the task edit
 * dialog and the recurring (schedule) edit dialog, so both render an identical
 * checklist (unified UI).
 *
 * Included by views/taskboard/partial/task/dialog.edit.checklist.php (fetched
 * over /task/checklist/:action/:task_id) and inline by the schedule dialog.
 *
 * Parameters, set by the including view:
 * @var array<int,array{status?:string,description?:string}> $checklistItems
 *      Rows to render; missing or non-array input renders an empty editor.
 * @var string|null $checklistEmptyText
 *      Optional placeholder shown while the list has no rows (only the
 *      schedule template uses one — the task dialog has none). ChecklistManager
 *      re-injects it after add/remove from the container's data-empty-text.
 *
 * The ids (#checklist-items, #add-item) are the hooks ChecklistManager relies
 * on (task.board.js); when two dialogs are in the DOM it targets the one inside
 * the visible .modal-container, so every dialog using this partial must keep
 * those ids.
 */
$checklistItems = is_array($checklistItems ?? null) ? $checklistItems : [];
$checklistEmptyText = isset($checklistEmptyText) && is_string($checklistEmptyText) ? $checklistEmptyText : null;
?>
<div class="flex-cell task-checklist-container">
    <label>Checklist:</label><br>
    <div id="checklist-container">
        <div class="flex-table" id="checklist-items"<?= $checklistEmptyText !== null ? ' data-empty-text="' . Sanitize::e($checklistEmptyText) . '"' : '' ?>>
            <?php foreach ($checklistItems as $index => $item): ?>
                <div class="flex-row">
                    <div class="flex-cell flex-cell-shrink flex-cell-vcenter">
                        <input type="checkbox" name="checklist[<?= (int)$index ?>][status]" value="complete" <?= (($item['status'] ?? '') === 'complete' ? 'checked' : '') ?>/>
                    </div>
                    <div class="flex-cell flex-cell-vcenter">
                        <?php
                        // Task checklists are stored HTML-escaped — TaskController::
                        // validateChecklist() runs Sanitize::e() before json_encode — so
                        // decode once before escaping for output, or "&amp;" renders
                        // literally. A schedule template created from a task inherits
                        // those same encoded values, hence the shared treatment here.
                        $description = html_entity_decode((string)($item['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        ?>
                        <input type="text" name="checklist[<?= (int)$index ?>][description]" value="<?= Sanitize::e($description) ?>"/>
                    </div>
                    <div class="flex-cell flex-cell-shrink flex-cell-vcenter">
                        <button type="button" tabindex="-1" class="remove-item btn btn-dark-gray btn-hover-red" title="Remove item">X</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($checklistEmptyText !== null && empty($checklistItems)): ?>
                <div class="checklist-empty"><?= Sanitize::e($checklistEmptyText) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <button type="button" id="add-item" class="btn btn-light-gray checklist-add-item">+ Add item</button>
</div>
