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

    /** Refresh the jobs list */
    public const REFRESH_JOBS_LIST = 'refreshJobsList';

    /** Refresh the job stats panel */
    public const REFRESH_JOB_STATS = 'refreshJobStats';

    /** Re-fetch the content of the modal the trigger originated from */
    public const REFRESH_THIS_MODAL = 'refreshThisModal';

    /** Refresh the sticky note category list */
    public const REFRESH_NOTE_CATEGORY_LIST = 'triggerNoteCatlist';

    /** Refresh the scheduled-tasks (recurring) list dialog */
    public const REFRESH_SCHEDULE_LIST = 'refreshScheduleList';

    /** Close the recurring-schedule slide-out panel (panel.recurrence) */
    public const CLOSE_RECURRENCE_PANEL = 'closeRecurrencePanel';

    /** Attachment list changed (payload: ['itemType' => string, 'itemId' => int]);
     *  refresh the attachments tab/list for that item */
    public const ATTACHMENTS_UPDATE = 'attachmentsUpdate';

    private function __construct()
    {
        // Static class - not instantiable
    }

    /**
     * Build a CLOSE_SPECIFIC_MODAL trigger payload for the given modal IDs.
     *
     * @param string ...$modalIds IDs of the modal(s) to close, e.g. 'dialog-note'
     * @return array Ready to merge into a trigger payload
     */
    public static function closeSpecificModal(string ...$modalIds): array
    {
        return [self::CLOSE_SPECIFIC_MODAL => array_values($modalIds)];
    }

    /**
     * Build a GLOBAL_MESSAGE (toast) trigger payload.
     *
     * @param string $type    'success' or 'error'
     * @param string $message Message shown in the toast
     * @return array Ready to merge into a trigger payload
     */
    public static function globalMessage(string $type, string $message): array
    {
        return [self::GLOBAL_MESSAGE => ['type' => $type, 'message' => $message]];
    }

    /**
     * Build a success toast trigger payload.
     */
    public static function successToast(string $message): array
    {
        return self::globalMessage('success', $message);
    }

    /**
     * Build an error toast trigger payload.
     */
    public static function errorToast(string $message): array
    {
        return self::globalMessage('error', $message);
    }

    /**
     * Return all event constants as a name => event-value map.
     * Used to emit the JS-side window.HTMX_EVENTS object so PHP and JS
     * can never drift apart.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return $reflection->getConstants();
    }

    /**
     * Build a standard success trigger payload: toast message plus any
     * extra events (e.g. list refresh, close modal).
     *
     * @param string $message       Toast message shown to the user
     * @param array  $extraTriggers Additional HX-Trigger events, e.g. [HtmxEvents::CLOSE_MODAL => true]
     * @return array Ready to pass to triggerResponse()
     */
    public static function successResponse(string $message, array $extraTriggers = []): array
    {
        return array_merge($extraTriggers, [
            self::GLOBAL_MESSAGE => ['type' => 'success', 'message' => $message],
        ]);
    }

    /**
     * Build a standard error trigger payload: error toast plus any extra events.
     *
     * @param string $message       Error toast message shown to the user
     * @param array  $extraTriggers Additional HX-Trigger events
     * @return array Ready to pass to triggerResponse()
     */
    public static function errorResponse(string $message, array $extraTriggers = []): array
    {
        return array_merge($extraTriggers, [
            self::GLOBAL_MESSAGE => ['type' => 'error', 'message' => $message],
        ]);
    }

    /**
     * Send a success response and exit: toast + optional extra triggers.
     * Convenience for the common view pattern:
     *   triggerResponse(HtmxEvents::success('Task saved!', [HtmxEvents::CLOSE_MODAL => true]));
     */
    public static function success(string $message, array $extraTriggers = []): never
    {
        triggerResponse(self::successResponse($message, $extraTriggers));
        exit;
    }

    /**
     * Send an error response and exit: toast + optional extra triggers.
     */
    public static function error(string $message, array $extraTriggers = []): never
    {
        triggerResponse(self::errorResponse($message, $extraTriggers));
        exit;
    }
}
