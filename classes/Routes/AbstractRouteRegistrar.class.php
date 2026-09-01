<?php
namespace Dashboard\Routes;

use Dashboard\Core\Router;
use Dashboard\Core\AuthMiddleware;

/**
 * Route registrar base class.
 *
 * Each module defines its routes in register() using $router->addRoutes()
 * batch definitions. Middleware defaults to the shared AuthMiddleware
 * unless a route overrides it.
 */
abstract class AbstractRouteRegistrar {
    protected Router $router;
    protected AuthMiddleware $auth;

    public function __construct(Router $router, AuthMiddleware $auth) {
        $this->router = $router;
        $this->auth = $auth;
    }

    abstract public function register(): void;

    /**
     * Shorthand: auth middleware for every route in the module.
     */
    protected function auth(): array {
        return [$this->auth];
    }
}
