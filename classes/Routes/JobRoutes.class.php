<?php
namespace Dashboard\Routes;

/**
 * Jobs dashboard routes.
 */
class JobRoutes extends AbstractRouteRegistrar {
    public function register(): void {
        $this->router->addRoutes([
            [['GET', 'POST', 'DELETE'], '/jobs/dialog/:action', 'jobs/partial/dialog.job', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST', 'DELETE'], '/jobs/dialog/:action/:job_id', 'jobs/partial/dialog.job', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/jobs/table', 'jobs/partial/table', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/jobs/stats', 'jobs/partial/stats', 'type' => 'partial', 'middleware' => $this->auth()],
            ['POST', '/jobs/batch', 'jobs/partial/batch.handler', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);
    }
}
