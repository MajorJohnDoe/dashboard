<?php
namespace Dashboard\Routes;

/**
 * Notification routes (controller-based partials).
 */
class NotificationRoutes extends AbstractRouteRegistrar {
    public function register(): void {
        $this->router->addRoutes([
            ['GET', '/notifications', 'Core/NotificationsController@panel', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/notifications/list', 'Core/NotificationsController@list', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/notifications/list/:filter', 'Core/NotificationsController@list', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/notifications/check', 'Core/NotificationsController@check', 'type' => 'partial', 'middleware' => $this->auth()],
            ['POST', '/notifications/mark-read', 'Core/NotificationsController@markRead', 'type' => 'partial', 'middleware' => $this->auth()],
            ['POST', '/notifications/mark-all-read', 'Core/NotificationsController@markAllRead', 'type' => 'partial', 'middleware' => $this->auth()],
            ['DELETE', '/notifications/delete', 'Core/NotificationsController@delete', 'type' => 'partial', 'middleware' => $this->auth()],
            ['POST', '/notifications/accept', 'Core/NotificationsController@accept', 'type' => 'partial', 'middleware' => $this->auth()],
            ['POST', '/notifications/decline', 'Core/NotificationsController@decline', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);
    }
}
