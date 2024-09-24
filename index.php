<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering at the very beginning
ob_start(); 

require_once('autoload.php');

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
$router->addRoute('GET', '/',               'taskboard/page/index',             ['title' => 'Task Dashboard', 'css' => ['layout'], 'js' => [], 'full_page' => true], [$authMiddleware]);
$router->addRoute('GET', '/board',          'taskboard/page/view_taskboard',    ['title' => 'Task Management Board', 'css' => ['layout', 'task'], 'js' => ['task.board'],'external_js' => ['/node_modules/sortablejs/Sortable.min.js', '/node_modules/tinymce/tinymce.min.js'], 'full_page' => true], [$authMiddleware]);
$router->addRoute('GET', '/stickynotes',    'stickynote/page/index',            ['title' => 'Sticky Notes', 'css' => ['layout', 'stickynotes'], 'js' => [], 'external_js' => ['/node_modules/tinymce/tinymce.min.js'], 'full_page' => true], [$authMiddleware]);

// Login/logout route (no authentication middleware)
$router->addRoute(['GET', 'POST'], '/login', 'core/login', ['title' => 'Login', 'full_page' => false]);
$router->addRoute(['GET', 'POST'], '/logout', 'core/logout', ['title' => 'Login', 'full_page' => false]);


// ****************************************************************
// Partial routes, Core routes
// ****************************************************************
$router->addPartialRoute(['GET', 'POST'], '/account/settings',   'core/partial/modal.profile', [$authMiddleware]);           // create, edit, delete a note dialog


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
$router->addPartialRoute('GET',                         '/task/label/search',                       'taskboard/partial/task/label.search', [$authMiddleware]);                          // search for task labels
$router->addPartialRoute(['GET', 'POST'],               '/task/duplicate/:action/:taskid',          'taskboard/partial/task/dialog.edit.duplicate', [$authMiddleware]);                 // Task duplicate button/modal
$router->addPartialRoute('GET',                         '/task/checklist/:action/:task_id',         'taskboard/partial/task/dialog.edit.checklist', [$authMiddleware]);                 // Task checklist import
$router->addPartialRoute('POST',                        '/task/move-to-column',                     'Taskboard\TaskController@handleDragAndDropTaskColumns', [$authMiddleware]);        // Drag and drop task endpoint

// 4. Board Management
// Functionality for managing task boards, including columns
$router->addPartialRoute(['GET', 'POST'], '/board/dialog/columns/:action',  'taskboard/partial/board/dialog.edit.columns', [$authMiddleware]);  // Add column in board modal
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

/* error_log("Handling request: $method $path"); */

try {
    $content = $router->handleRequest($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    echo $content;
} catch (Exception $e) {
    error_log("Error occurred: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo "An error occurred. Please try again later.";
}

if (ob_get_length()) ob_end_flush(); // End output buffering if it's not empty
?>