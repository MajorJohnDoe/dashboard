<?php
/**
 * Job Batch Actions Handler Partial
 * Processes bulk operations: status change, archive, delete.
 */

use Dashboard\Core\HtmxEvents;
use Dashboard\Jobs\JobController;

// CSRF is enforced centrally by CsrfMiddleware via the Router
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobController = new JobController($db, $user);
    $result = $jobController->handleBatchAction($_POST);

    if ($result['success']) {
        triggerResponse(HtmxEvents::successResponse($result['message'], [
            HtmxEvents::REFRESH_JOBS_LIST => true,
            HtmxEvents::REFRESH_JOB_STATS => true,
        ]));
    } else {
        triggerResponse(HtmxEvents::errorResponse($result['message'] ?? 'Batch action failed.'));
    }
}
