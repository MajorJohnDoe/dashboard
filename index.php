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
use Dashboard\Routes\CoreRoutes;
use Dashboard\Routes\NotificationRoutes;
use Dashboard\Routes\TaskboardRoutes;
use Dashboard\Routes\StickynoteRoutes;
use Dashboard\Routes\JobRoutes;

$db = new Database($mysql_server, $mysql_user, $mysql_password, $mysql_database_name, _ERROR_REPORTING_MYSQL);
$session = new SecureSession();
$session->open();
$user = new User($db, $session);
$view = new View();
$router = new Router($db, $user, $view, $session);
$authMiddleware = new AuthMiddleware($user);

// Auth applies to every route by default; individual routes can opt out
// (e.g. login) by simply not adding middleware.
$router->setDefaultMiddleware([$authMiddleware]);

// -------------------------------------------------------------------------
// Route Registration
// Each application module registers its own routes. To add a new module,
// create a registrar in classes/Routes/ and add it to the list below.
// -------------------------------------------------------------------------
$registrarClasses = [
    CoreRoutes::class,          // full pages, login/logout, account settings
    NotificationRoutes::class,  // notification panel/list/actions
    TaskboardRoutes::class,     // boards, columns, tasks, labels, calendar, sharing
    StickynoteRoutes::class,    // sticky notes, categories, audio transcribe, search
    JobRoutes::class,           // job applications
];

foreach ($registrarClasses as $registrarClass) {
    (new $registrarClass($router, $authMiddleware))->register();
}

// -------------------------------------------------------------------------
// Request Handling
// Process the incoming request and route it to the appropriate handler
// -------------------------------------------------------------------------
try {
    $content = $router->handleRequest($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    echo $content;
} catch (\Throwable $e) {
    error_log("Request error: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    // Never leak internal exception details to the client.
    echo json_encode(['error' => 'Internal server error']);
}

if (ob_get_length()) ob_end_flush();
