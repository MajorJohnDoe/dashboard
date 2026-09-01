<?php
namespace Dashboard\Core;

/**
 * Central registry of HTMX event names exchanged between PHP (HX-Trigger
 * header via triggerResponse()) and JavaScript (document.body listeners).
 *
 * Always reference these constants instead of hardcoding event strings so
 * PHP and JS stay in sync.
 */
final class HtmxEvents
{
    /** Refresh the task board column list */
    public const TASK_BOARD_COLUMN_LIST = 'taskBoardColumnList';

    /** Close the currently active modal (payload: true or ['modalId' => string]) */
    public const CLOSE_MODAL = 'closeModalEvent';

    /** Close specific modal(s) (payload: array of modal IDs) */
    public const CLOSE_SPECIFIC_MODAL = 'closeSpecificModalEvent';

    /** Re-fetch the content of the currently open modal */
    public const REFRESH_MODAL = 'refreshModal';

    /** Refresh the task history calendar view */
    public const REFRESH_TASK_HISTORY = 'refreshTaskHistory';

    /** Show a toast message (payload: ['type' => 'success'|'error', 'message' => string]) */
    public const GLOBAL_MESSAGE = 'globalMessagePopupUpdate';

    /** Refresh the sticky note list */
    public const TRIGGER_NOTELIST = 'triggerNotelist';

    /** Refresh the label form / label search results */
    public const TRIGGER_LABEL_FORM = 'triggerLabelForm';

    /** Refresh the label search input results */
    public const SEARCH_LABEL_EDIT = 'search-label-edit';

    /** Refresh the profile/settings modal */
    public const REFRESH_PROFILE_MODAL = 'refreshProfileModal';

    /** Notification counts/panel changed */
    public const NOTIFICATIONS_UPDATE = 'notificationsUpdate';

    /** Refresh the notifications panel dialog */
    public const REFRESH_NOTIFICATIONS_DIALOG = 'refreshNotificationsDialog';

    /** A board was created or changed; refresh board lists */
    public const NEW_BOARD = 'newBoard';

    private function __construct()
    {
        // Static class - not instantiable
    }
}
