<?php
/**
 * Job Batch Actions Handler Partial
 * Processes bulk operations: status change, archive, delete.
 */

use Dashboard\Jobs\JobController;

// CSRF is enforced centrally by CsrfMiddleware via the Router
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobController = new JobController($db, $user);
    $result = $jobController->handleBatchAction($_POST);

    if ($result['success']) {
        triggerResponse([
            "refreshJobsList" => true,
            "refreshJobStats" => true,
            \Dashboard\Core\HtmxEvents::GLOBAL_MESSAGE => [
                'type' => 'success',
                'message' => $result['message']
            ]
        ]);
    } else {
        triggerResponse([
            \Dashboard\Core\HtmxEvents::GLOBAL_MESSAGE => [
                'type' => 'error',
                'message' => $result['message'] ?? 'Batch action failed.'
            ]
        ]);
    }
}
