<?php
namespace Dashboard\Routes;

/**
 * Core routes: full pages (dashboard, board, stickynotes, jobs), login/logout,
 * and account settings.
 */
class CoreRoutes extends AbstractRouteRegistrar {
    public function register(): void {
        $this->router->addRoutes([
            // Full page routes
            ['GET', '/', 'taskboard/page/index', 'type' => 'page', 'options' => [
                'title' => 'Task Dashboard', 'css' => ['layout', 'task'], 'js' => ['notifications'], 'full_page' => true,
            ], 'middleware' => $this->auth()],
            ['GET', '/board', 'taskboard/page/view_taskboard', 'type' => 'page', 'options' => [
                'title' => 'Task Management Board', 'css' => ['layout', 'task', 'context.menu'], 'js' => ['task.board', 'notifications'],
                'external_js' => ['/node_modules/sortablejs/Sortable.min.js', '/node_modules/tinymce/tinymce.min.js'], 'full_page' => true,
            ], 'middleware' => $this->auth()],
            ['GET', '/stickynotes', 'stickynote/page/index', 'type' => 'page', 'options' => [
                'title' => 'Sticky Notes', 'css' => ['layout', 'stickynotes'], 'js' => ['notifications'],
                'external_js' => ['/node_modules/tinymce/tinymce.min.js'], 'full_page' => true,
            ], 'middleware' => $this->auth()],
            ['GET', '/jobs', 'jobs/page/index', 'type' => 'page', 'options' => [
                'title' => 'Job Applications', 'css' => ['layout', 'jobs'], 'js' => ['jobs', 'notifications'],
                'external_js' => ['/node_modules/tinymce/tinymce.min.js'], 'full_page' => true,
            ], 'middleware' => $this->auth()],

            // Login/logout (no auth middleware, CSRF exempt — no session token exists pre-login)
            [['GET', 'POST'], '/login', 'core/login', 'type' => 'page', 'options' => ['title' => 'Login', 'full_page' => false, 'csrf' => false]],
            [['GET', 'POST'], '/logout', 'core/logout', 'type' => 'page', 'options' => ['title' => 'Login', 'full_page' => false]],

            // Account settings modal
            [['GET', 'POST'], '/account/settings', 'core/partial/modal.profile', 'type' => 'partial', 'middleware' => $this->auth()],

            // Per-user UI preferences: edit-dialog tab order (drag to reorder)
            ['POST', '/ui/tab-order', 'Core\UiPreferenceController@handleSaveTabOrder', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);
    }
}
