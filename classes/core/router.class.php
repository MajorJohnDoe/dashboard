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
    private $csrfMiddleware;
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
        $this->csrfMiddleware = new CsrfMiddleware();
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

        // Enforce CSRF protection on state-changing requests (unless opted out)
        $csrfEnabled = !isset($route['options']['csrf']) || $route['options']['csrf'] !== false;
        if ($csrfEnabled && !$this->csrfMiddleware->handle()) {
            return;
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
        try {
            list($controllerName, $methodName) = explode('@', $handler);
            // Convert forward slashes to backslashes for namespace
            $controllerClass = "Dashboard\\" . str_replace('/', '\\', $controllerName);
            
            if (!class_exists($controllerClass)) {
                throw new Exception("Controller class $controllerClass not found");
            }
            
            $controller = new $controllerClass($this->db, $this->user, $this->view);
            $result = $controller->$methodName();
            
            // Check if controller returned a view response
            if (is_array($result) && isset($result['view'])) {
                // Render view with data
                $viewData = [
                    'db' => $this->db,
                    'user' => $this->user,
                    'session' => $this->session,
                    'options' => ['full_page' => false]
                ];
                // Merge controller data into viewData
                if (isset($result['data'])) {
                    $viewData = array_merge($viewData, $result['data']);
                }
                return $this->view->render($result['view'], $viewData);
            }
            
            // Default: JSON response for existing controllers
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        } catch (\Throwable $e) {
            error_log("Controller error: " . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle a view-based route
     */
    private function handleViewRoute($route) {
        $viewData = [
            'db' => $this->db,
            'user' => $this->user,
            'session' => $this->session,
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
        
        // Pass db/user so full-page views (header.php) can access them
        return $this->view->render('404', [
            'db' => $this->db,
            'user' => $this->user,
            'session' => $this->session,
            'options' => ['title' => '404 Not Found', 'full_page' => true]
        ]);
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
        if (file_exists($fullPath)) {
            $db = $this->db;
            $user = $this->user;
            $_GET = array_merge($_GET, $params);
            ob_start();
            require $fullPath;
            $content = ob_get_clean();
            return $content;
        } else {
            throw new Exception("View file not found: $fullPath");
        }
    }
}
?>
