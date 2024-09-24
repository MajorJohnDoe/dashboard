<?php
namespace Dashboard\Core;

use Exception;
use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;
use Dashboard\Core\AuthMiddleware;
use Dashboard\Core\SecureSession;

class Router {
    // Dependencies
    private $db;
    private $user;
    private $view;
    private $authMiddleware;
    private $session;

    // Storage for routes
    private $routes = [];

    /**
     * Constructor: Initialize the Router with its dependencies
     */
    public function __construct(DatabaseInterface $db, User $user, View $view, SecureSession $session) {
        $this->db = $db;
        $this->user = $user;
        $this->view = $view;
        $this->session = $session;
        $this->authMiddleware = new AuthMiddleware($user);
    }

    /**
     * Add a new route for a full page
     * 
     * @param string|array $method HTTP method(s) for this route
     * @param string $path URL path for this route
     * @param string|callable $handler The handler for this route
     * @param array $options Additional options for the route
     * @param array $middleware Middleware to be applied to this route
     */
    public function addRoute($method, $path, $handler, $options = [], $middleware = []) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'options' => array_merge(['full_page' => true], $options),
            'middleware' => $middleware
        ];
    }

    /**
     * Add a new route for a partial page (AJAX/HTMX)
     * 
     * @param string|array $method HTTP method(s) for this route
     * @param string $path URL path for this route
     * @param string|callable $handler The handler for this route
     * @param array $middleware Middleware to be applied to this route
     */
    public function addPartialRoute($method, $path, $handler, $middleware = []) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'options' => ['full_page' => false],
            'middleware' => $middleware
        ];
    }

    /**
     * Handle an incoming request
     * 
     * @param string $method The HTTP method of the request
     * @param string $path The URL path of the request
     * @return string|null The response content
     */
    public function handleRequest($method, $path) {
        error_log("Handling request: $method $path");

        foreach ($this->routes as $route) {
            if ($this->isMethodMatch($route['method'], $method)) {
                $params = $this->matchPath($route['path'], $path);
                if ($params !== false) {
                    return $this->processRoute($route, $params);
                }
            }
        }
        
        return $this->handleNotFound($path);
    }

    /**
     * Check if the route method matches the request method
     */
    private function isMethodMatch($routeMethod, $requestMethod) {
        return (is_array($routeMethod) && in_array($requestMethod, $routeMethod)) || $routeMethod === $requestMethod;
    }

    /**
     * Process a matched route
     */
    private function processRoute($route, $params) {
        $_GET = array_merge($_GET, $params);

        // Apply middleware
        foreach ($route['middleware'] as $middleware) {
            if (!$middleware->handle()) {
                return;
            }
        }

        if ($this->isControllerRoute($route['handler'])) {
            return $this->handleControllerRoute($route['handler']);
        } else {
            return $this->handleViewRoute($route);
        }
    }

    /**
     * Check if the route handler is a controller method
     */
    private function isControllerRoute($handler) {
        return is_string($handler) && strpos($handler, '@') !== false;
    }

    /**
     * Handle a controller-based route
     */
    private function handleControllerRoute($handler) {
        list($controllerName, $methodName) = explode('@', $handler);
        $controllerClass = "Dashboard\\" . $controllerName;
        
        if (!class_exists($controllerClass)) {
            throw new Exception("Controller class $controllerClass not found");
        }
        
        $controller = new $controllerClass($this->db, $this->user);
        $result = $controller->$methodName();
        
        // Ensure proper JSON response
        header('Content-Type: application/json');
        echo json_encode($result);
        return;
    }

    /**
     * Handle a view-based route
     */
    private function handleViewRoute($route) {
        $viewData = [
            'db' => $this->db,
            'user' => $this->user,
            'session' => $this->session, // Add this line
            'options' => $route['options']
        ];
        return $this->view->render($route['handler'], $viewData);
    }

    /**
     * Handle a 404 Not Found response
     */
    private function handleNotFound($path) {
        error_log("No route found for: $path");
        
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            http_response_code(404);
            return json_encode(['error' => 'Not Found', 'path' => $path]);
        }
        
        return $this->view->render('404', ['options' => ['title' => '404 Not Found', 'full_page' => true]]);
    }

    /**
     * Check if the current request is an AJAX or HTMX request
     */
    private function isAjaxRequest() {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            || (!empty($_SERVER['HTTP_HX_REQUEST']));
    }

    /**
     * Match the route path against the request path
     * 
     * @param string $routePath The defined route path
     * @param string $requestPath The actual request path
     * @return array|false An array of path parameters if matched, false otherwise
     */
    private function matchPath($routePath, $requestPath) {
        error_log("Matching route: $routePath against request: $requestPath");
        $routeParts = explode('/', trim($routePath, '/'));
        $requestParts = explode('/', trim(strtok($requestPath, '?'), '/'));
    
        if (count($routeParts) !== count($requestParts)) {
            return false;
        }
    
        $params = [];
        for ($i = 0; $i < count($routeParts); $i++) {
            if (strpos($routeParts[$i], ':') === 0) {
                $paramName = substr($routeParts[$i], 1);
                $params[$paramName] = $requestParts[$i];
            } elseif ($routeParts[$i] !== $requestParts[$i]) {
                return false;
            }
        }
    
        return $params;
    }

    /**
     * Load a view file
     * 
     * @param string $viewPath The path to the view file
     * @param array $params Parameters to be passed to the view
     * @return string The rendered view content
     * @throws Exception If the view file is not found
     */
    private function loadView($viewPath, $params = []) {
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/views/' . $viewPath . '.php';
        error_log("Attempting to load view: $fullPath");
        if (file_exists($fullPath)) {
            $db = $this->db;
            $user = $this->user;
            $_GET = array_merge($_GET, $params);
            error_log("View file found, including it now");
            ob_start();
            require $fullPath;
            $content = ob_get_clean();
            error_log("View content length: " . strlen($content));
            return $content;
        } else {
            error_log("View file not found: $fullPath");
            throw new Exception("View file not found: $fullPath");
        }
    }
}
?>