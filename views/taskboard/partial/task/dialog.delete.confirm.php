<?php
use Dashboard\Core\HtmxEvents;
?>
<!-- Delete confirmation dialog (opened from the task context menu).
     Follows the standard modal-container pattern from layout.css. -->
<div id="dialog-task-delete-confirm"
     class="modal-container">
    <div class="dialog dialog-sm">
        <div class="dialog-header">
            <span>Delete task</span>
            <button class="close-modal-btn btn">X</button>
        </div>
        <div class="formOuter">
            <p style="padding: 0 1rem;">
                Are you sure you want to delete this task? This cannot be undone.
            </p>
        </div>
        <div class="form-actions">
            <div class="flex-table">
                <div class="flex-cell">
                    <button type="button" class="btn btn-light-gray close-modal-btn">Cancel</button>
                </div>
                <div class="flex-cell flex-vertical-center flex-right">
                    <button type="button"
                            class="btn btn-hover-red"
                            hx-delete="/task/dialog/edit/<?= (int)$_GET['column_id'] ?>/<?= (int)$_GET['task_id'] ?>"
                            hx-target="body"
                            hx-swap="beforeend">Delete task</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // Open immediately when swapped into the body (ModalManager only opens
    // elements on click; this dialog is fetched programmatically).
    (function () {
        var modal = document.getElementById('dialog-task-delete-confirm');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('show');
        }
    })();
</script>