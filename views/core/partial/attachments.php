<?php
use Dashboard\Core\Sanitize;

/**
 * Shared attachments tab content partial.
 *
 * Parameterized by the including view:
 * @var string $attachmentItemType  'task' | 'stickynote' | 'job'
 * @var int    $attachmentItemId    ID of the item; 0 = item not created yet
 *                                  (uploads are staged as session pending files,
 *                                  claimed when the item is created)
 * @var int    $attachmentCount     Pre-computed count (0 hides the tab badge)
 *
 * Renders the upload form (dropzone + file input) and lazily loads the
 * file list from /attachment/list/:item_type/:item_id.
 *
 * NOTE: the file input / upload button ids are only unique per dialog; two
 * attachment panes on one page (e.g. task + schedule dialogs) must not both
 * be open at once, matching the existing checklist-id precedent.
 */
$attachmentItemType = $attachmentItemType ?? 'task';
$attachmentItemId = (int)($attachmentItemId ?? 0);
$attachmentCount = (int)($attachmentCount ?? 0);
$attachmentListUrl = '/attachment/list/' . $attachmentItemType . '/' . $attachmentItemId;
?>
<div class="attachments-pane" data-item-type="<?= Sanitize::e($attachmentItemType) ?>" data-item-id="<?= $attachmentItemId ?>">
    <div class="attachment-upload-row">
        <input type="file"
               class="attachment-file-input"
               accept=".pdf,.txt,.docx,.xlsx"
               data-upload-url="/attachment/upload/<?= Sanitize::e($attachmentItemType) ?>/<?= $attachmentItemId ?>"
               tabindex="-1">
        <button type="button" class="btn btn-dark-gray attachment-upload-btn" tabindex="-1">
            Add file&hellip;
        </button>
        <span class="attachment-hint">PDF, txt, docx, xlsx &middot; max <?= round(effectiveUploadMaxBytes() / 1048576) ?> MB</span>
    </div>

    <div class="attachment-progress" hidden>
        <div class="attachment-progress-bar"></div>
        <span class="attachment-progress-label">Uploading&hellip;</span>
    </div>

    <div class="attachment-list-container"
         hx-get="<?= Sanitize::e($attachmentListUrl) ?>"
         hx-trigger="load, attachmentsUpdate from:body"
         hx-target="this"
         hx-swap="innerHTML">
    </div>

    <?php if ($attachmentItemId === 0): ?>
    <!-- Pending claim tokens: JS appends one hidden field per staged file
         after upload; posted with the item-create form and validated against
         the session server-side. -->
    <div class="attachment-pending-tokens"></div>
    <?php endif; ?>
</div>
