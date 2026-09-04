<?php
namespace Dashboard\Routes;

/**
 * Taskboard routes: board sharing, labels, tasks, boards, columns, calendar.
 */
class TaskboardRoutes extends AbstractRouteRegistrar {
    public function register(): void {
        // Board sharing
        $this->router->addRoutes([
            ['GET', '/board/members', 'taskboard/partial/board/members.list', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/board/share', 'taskboard/partial/board/share.handler', 'type' => 'partial', 'middleware' => $this->auth()],
            [['PUT'], '/board/share/access', 'taskboard/partial/board/share.handler', 'type' => 'partial', 'middleware' => $this->auth()],
            [['DELETE'], '/board/share', 'taskboard/partial/board/share.handler', 'type' => 'partial', 'middleware' => $this->auth()],
            [['POST'], '/board/share/accept', 'taskboard/partial/board/share.handler', 'type' => 'partial', 'middleware' => $this->auth()],
            [['POST'], '/board/share/decline', 'taskboard/partial/board/share.handler', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);

        // Label management
        $this->router->addRoutes([
            ['GET', '/label/search', 'taskboard/partial/label/dialog.edit.search', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST', 'DELETE'], 'taskboard/label/form/:action', 'taskboard/partial/label/dialog.edit.label.form', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/label/:action', 'taskboard/partial/label/dialog.edit', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);

        // Task management
        $this->router->addRoutes([
            // Delete confirmation dialog (context menu) — must be registered
            // before the wildcard /task/dialog/:action/... routes below.
            ['GET', '/task/dialog/delete-confirm/:column_id/:task_id', 'taskboard/partial/task/dialog.delete.confirm', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/task/dialog/:action/:column_id', 'taskboard/partial/task/dialog.edit', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST', 'DELETE'], '/task/dialog/:action/:column_id/:task_id', 'taskboard/partial/task/dialog.edit', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/task/label/search', 'taskboard/partial/task/label.search', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/task/duplicate/:action/:taskid', 'taskboard/partial/task/dialog.edit.duplicate', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/task/checklist/:action/:task_id', 'taskboard/partial/task/dialog.edit.checklist', 'type' => 'partial', 'middleware' => $this->auth()],
            ['POST', '/task/move-to-column', 'Taskboard\TaskController@handleDragAndDropTaskColumns', 'type' => 'partial', 'middleware' => $this->auth()],
            // Quick priority change (context menu)
            ['POST', '/task/priority/:task_id', 'Taskboard\TaskController@handleSetPriorityRequest', 'type' => 'partial', 'middleware' => $this->auth()],
            // Recurring schedule panel (opened from task edit modal / context menu)
            ['GET', '/task/recurrence/panel/:task_id', 'taskboard/partial/schedule/panel.recurrence', 'type' => 'partial', 'middleware' => $this->auth()],
            ['POST', '/task/recurrence/save/:task_id', 'taskboard/partial/schedule/panel.recurrence', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);

        // Board management
        $this->router->addRoutes([
            [['GET', 'POST'], '/board/dialog/columns/:action', 'taskboard/partial/board/dialog.edit.columns', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/board/dialog/new', 'taskboard/partial/board/dialog.new', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/board/dialog/:action', 'taskboard/partial/board/dialog.edit', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/board/list', 'taskboard/partial/board/list.boards', 'type' => 'partial', 'middleware' => $this->auth()],
            ['POST', '/board/create', 'Taskboard\BoardController@createBoard', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/board/select', 'Taskboard\BoardController@selectBoard', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);

        // Column management
        $this->router->addRoutes([
            [['GET', 'POST', 'DELETE'], '/column/dialog/:action/:column_id', 'taskboard/partial/column/dialog.edit', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/column/list', 'taskboard/partial/column/list.columns', 'type' => 'partial', 'middleware' => $this->auth()],
            ['POST', '/column/save-column-order', 'Taskboard\ColumnController@handleDragDropColumnOrder', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);

        // Calendar view
        $this->router->addRoutes([
            ['GET', '/calendar/dialog/init/:init', 'taskboard/partial/calendar/dialog', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/calendar/dialog/date/:date', 'taskboard/partial/calendar/dialog', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);

        // Scheduled (recurring) tasks
        $this->router->addRoutes([
            ['GET', '/schedule/dialog/init', 'taskboard/partial/schedule/dialog.list', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/schedule/dialog/new/:board_id', 'taskboard/partial/schedule/dialog.edit', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/schedule/dialog/edit/:board_id/:schedule_id', 'taskboard/partial/schedule/dialog.edit', 'type' => 'partial', 'middleware' => $this->auth()],
            ['POST', '/schedule/toggle/:schedule_id', 'taskboard/partial/schedule/dialog.list', 'type' => 'partial', 'middleware' => $this->auth()],
            ['DELETE', '/schedule/delete/:schedule_id', 'taskboard/partial/schedule/dialog.list', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);
    }
}
