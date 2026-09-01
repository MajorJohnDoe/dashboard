<?php

// Start output buffering at the very beginning
ob_start(); 

require_once('autoload.php');

// Debug mode is controlled by _APP_DEBUG in config.php - never display errors on a live site
if (defined('_APP_DEBUG') && _APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
}

use Dashboard\Core\Router;
use Dashboard\Core\Database;
use Dashboard\Core\SecureSession;
use Dashboard\Core\User;
use Dashboard\Core\View;
use Dashboard\Core\AuthMiddleware;

$db = new Database($mysql_server, $mysql_user, $mysql_password, $mysql_database_name, _ERROR_REPORTING_MYSQL);
$session = new SecureSession();
$session->open();
$user = new User($db, $session);
$view = new View();
$router = new Router($db, $user, $view, $session);
$authMiddleware = new AuthMiddleware($user);

// -------------------------------------------------------------------------
// Route Definitions
// These define the structure and functionality of the Hyperboard application
// -------------------------------------------------------------------------

// Full page routes
$router->addRoute('GET', '/',               'taskboard/page/index',             ['title' => 'Task Dashboard', 'css' => ['layout'], 'js' => ['notifications'], 'full_page' => true], [$authMiddleware]);
$router->addRoute('GET', '/board',          'taskboard/page/view_taskboard',    ['title' => 'Task Management Board', 'css' => ['layout', 'task'], 'js' => ['task.board', 'notifications'],'external_js' => ['/node_modules/sortablejs/Sortable.min.js', '/node_modules/tinymce/tinymce.min.js'], 'full_page' => true], [$authMiddleware]);
$router->addRoute('GET', '/stickynotes',    'stickynote/page/index',            ['title' => 'Sticky Notes', 'css' => ['layout', 'stickynotes'], 'js' => ['notifications'], 'external_js' => ['/node_modules/tinymce/tinymce.min.js'], 'full_page' => true], [$authMiddleware]);
$router->addRoute('GET', '/jobs',           'jobs/page/index',                  ['title' => 'Job Applications', 'css' => ['layout', 'jobs'], 'js' => ['jobs', 'notifications'], 'external_js' => ['/node_modules/tinymce/tinymce.min.js'], 'full_page' => true], [$authMiddleware]);

// Login/logout route (no authentication middleware, CSRF exempt — no session token exists pre-login)
$router->addRoute(['GET', 'POST'], '/login', 'core/login', ['title' => 'Login', 'full_page' => false, 'csrf' => false]);
$router->addRoute(['GET', 'POST'], '/logout', 'core/logout', ['title' => 'Login', 'full_page' => false]);


// ****************************************************************
// Partial routes, Core routes
// ****************************************************************
$router->addPartialRoute(['GET', 'POST'], '/account/settings',   'core/partial/modal.profile', [$authMiddleware]);           // Account settings modal

// Notification routes - using MVC pattern
$router->addPartialRoute('GET', '/notifications', 'Core/NotificationsController@panel', [$authMiddleware]);
$router->addPartialRoute('GET', '/notifications/list', 'Core/NotificationsController@list', [$authMiddleware]);
$router->addPartialRoute('GET', '/notifications/list/:filter', 'Core/NotificationsController@list', [$authMiddleware]);
$router->addPartialRoute('GET', '/notifications/check', 'Core/NotificationsController@check', [$authMiddleware]);
$router->addPartialRoute('POST', '/notifications/mark-read', 'Core/NotificationsController@markRead', [$authMiddleware]);
$router->addPartialRoute('POST', '/notifications/mark-all-read', 'Core/NotificationsController@markAllRead', [$authMiddleware]);
$router->addPartialRoute('DELETE', '/notifications/delete', 'Core/NotificationsController@delete', [$authMiddleware]);
$router->addPartialRoute('POST', '/notifications/accept', 'Core/NotificationsController@accept', [$authMiddleware]);
$router->addPartialRoute('POST', '/notifications/decline', 'Core/NotificationsController@decline', [$authMiddleware]);

// Board sharing routes
$router->addPartialRoute('GET',           '/board/members',            'taskboard/partial/board/members.list', [$authMiddleware]);      // Board members list
$router->addPartialRoute(['GET', 'POST'], '/board/share',             'taskboard/partial/board/share.handler', [$authMiddleware]);     // Share board handler
$router->addPartialRoute(['PUT'],         '/board/share/access',      'taskboard/partial/board/share.handler', [$authMiddleware]);     // Update share access level
$router->addPartialRoute(['DELETE'],      '/board/share',             'taskboard/partial/board/share.handler', [$authMiddleware]);     // Remove board share
$router->addPartialRoute(['POST'],        '/board/share/accept',      'taskboard/partial/board/share.handler', [$authMiddleware]);     // Accept board invite
$router->addPartialRoute(['POST'],        '/board/share/decline',     'taskboard/partial/board/share.handler', [$authMiddleware]);     // Decline board invite


// ****************************************************************
// Sticky note dashboard routes
// ****************************************************************
$router->addPartialRoute(['GET', 'POST', 'DELETE'], '/stickynotes/note/:action/:note_id',       'stickynote/partial/note.dialog', [$authMiddleware]);           // create, edit, delete a note dialog
$router->addPartialRoute(['GET', 'POST', 'DELETE'], '/stickynotes/category/dialog/',            'stickynote/partial/category.dialog', [$authMiddleware]);       // create, edit, delete a category dialog
$router->addPartialRoute('GET',                     '/stickynotes/note-list',                   'stickynote/partial/note.list', [$authMiddleware]);
$router->addPartialRoute('GET',                     '/stickynotes/cat-list',                    'stickynote/partial/category.list', [$authMiddleware]);
$router->addPartialRoute(['GET', 'POST'],           '/stickynotes/audio-transcribe',            'stickynote/partial/audio.transcribe.dialog', [$authMiddleware]);
$router->addPartialRoute(['GET', 'POST'],           '/stickynotes/audio-transcribe/save',       'stickynote/partial/audio.transcribe.save', [$authMiddleware]);
$router->addPartialRoute(['GET', 'POST'],           '/stickynotes/audio-transcribe/process',    'stickynote/partial/audio.transcribe.process', [$authMiddleware]);

$router->addPartialRoute(['GET', 'POST'],           '/stickynotes/search',                      'stickynote/partial/note.search', [$authMiddleware]);
//$router->addPartialRoute('POST',                    '/stickynotes/upload-image',            'Stickynote\StickyNoteController@handleImageUpload', [$authMiddleware]);

// ****************************************************************
// Jobs dashboard routes
// ****************************************************************
$router->addPartialRoute(['GET', 'POST', 'DELETE'], '/jobs/dialog/:action',                     'jobs/partial/dialog.job', [$authMiddleware]);
$router->addPartialRoute(['GET', 'POST', 'DELETE'], '/jobs/dialog/:action/:job_id',             'jobs/partial/dialog.job', [$authMiddleware]);
$router->addPartialRoute('GET',                     '/jobs/table',                              'jobs/partial/table', [$authMiddleware]);
$router->addPartialRoute('GET',                     '/jobs/stats',                              'jobs/partial/stats', [$authMiddleware]);
$router->addPartialRoute('POST',                    '/jobs/batch',                              'jobs/partial/batch.handler', [$authMiddleware]);

// ****************************************************************
// Task dashboard routes
// ****************************************************************

// 2. Label Management
// Handles creation, editing, and searching of labels for tasks
$router->addPartialRoute('GET',                     '/label/search',                'taskboard/partial/label/dialog.edit.search', [$authMiddleware]);       // search labels dialog
$router->addPartialRoute(['GET', 'POST', 'DELETE'], 'taskboard/label/form/:action', 'taskboard/partial/label/dialog.edit.label.form', [$authMiddleware]);   // Add/remove labels form in labels modal
$router->addPartialRoute('GET',                     '/label/:action',               'taskboard/partial/label/dialog.edit', [$authMiddleware]);              // label edit in labels modal

// 3. Task Management
// Core functionality for creating, editing, and organizing tasks
$router->addPartialRoute(['GET', 'POST'],               '/task/dialog/:action/:column_id',          'taskboard/partial/task/dialog.edit', [$authMiddleware]);                           // new task dialog
$router->addPartialRoute(['GET', 'POST', 'DELETE'],     '/task/dialog/:action/:column_id/:task_id', 'taskboard/partial/task/dialog.edit', [$authMiddleware]);                           // edit existing task
$router->addPartialRoute(['GET', 'POST'],               '/task/label/search',                       'taskboard/partial/task/label.search', [$authMiddleware]);                          // search for task labels
$router->addPartialRoute(['GET', 'POST'],               '/task/duplicate/:action/:taskid',          'taskboard/partial/task/dialog.edit.duplicate', [$authMiddleware]);                 // Task duplicate button/modal
$router->addPartialRoute('GET',                         '/task/checklist/:action/:task_id',         'taskboard/partial/task/dialog.edit.checklist', [$authMiddleware]);                 // Task checklist import
$router->addPartialRoute('POST',                        '/task/move-to-column',                     'Taskboard\TaskController@handleDragAndDropTaskColumns', [$authMiddleware]);        // Drag and drop task endpoint

// 4. Board Management
// Functionality for managing task boards, including columns
$router->addPartialRoute(['GET', 'POST'], '/board/dialog/columns/:action',  'taskboard/partial/board/dialog.edit.columns', [$authMiddleware]);  // Add column in board modal
$router->addPartialRoute(['GET', 'POST'], '/board/dialog/new',                'taskboard/partial/board/dialog.new', [$authMiddleware]);          // Create new board modal
$router->addPartialRoute(['GET', 'POST'], '/board/dialog/:action',          'taskboard/partial/board/dialog.edit', [$authMiddleware]);          // Board modal
$router->addPartialRoute('GET',           '/board/list',                    'taskboard/partial/board/list.boards', [$authMiddleware]);          // List all task boards
$router->addPartialRoute('POST',          '/board/create',                  'Taskboard\BoardController@createBoard', [$authMiddleware]);        // Create a new board endpoint
$router->addPartialRoute('GET',           '/board/select',                  'Taskboard\BoardController@selectBoard', [$authMiddleware]);        // Select task board endpoint

// 5. Column Management
// Handles operations on individual columns within a board
$router->addPartialRoute(['GET', 'POST', 'DELETE'],     '/column/dialog/:action/:column_id', 'taskboard/partial/column/dialog.edit', [$authMiddleware]);
$router->addPartialRoute('GET',                         '/column/list',                      'taskboard/partial/column/list.columns', [$authMiddleware]);
$router->addPartialRoute('POST',                        '/column/save-column-order',         'Taskboard\ColumnController@handleDragDropColumnOrder', [$authMiddleware]); // Drag an drop column order endpoint

// 6. Calendar View
// Provides a calendar interface for task management
$router->addPartialRoute('GET', '/calendar/dialog/init/:init', 'taskboard/partial/calendar/dialog', [$authMiddleware]);
$router->addPartialRoute('GET', '/calendar/dialog/date/:date', 'taskboard/partial/calendar/dialog', [$authMiddleware]); 


// -------------------------------------------------------------------------
// Request Handling
// Process the incoming request and route it to the appropriate handler
// -------------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

try {
    $content = $router->handleRequest($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    echo $content;
} catch (\Throwable $e) {
    error_log("Request error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

if (ob_get_length()) ob_end_flush();
?>
