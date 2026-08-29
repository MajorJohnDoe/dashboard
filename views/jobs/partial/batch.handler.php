<?php
/**
 * Job Batch Actions Handler Partial
 * Processes bulk operations: status change, archive, delete.
 */

use Dashboard\Core\CsrfProtection;
use Dashboard\Jobs\JobController;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CsrfProtection::validateOrFail($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    $jobController = new JobController($db, $user);
    $result = $jobController->handleBatchAction($_POST);

    if ($result['success']) {
        triggerResponse([
            "refreshJobsList" => true,
            "refreshJobStats" => true,
            "globalMessagePopupUpdate" => [
                'type' => 'success',
                'message' => $result['message']
            ]
        ]);
    } else {
        triggerResponse([
            "globalMessagePopupUpdate" => [
                'type' => 'error',
                'message' => $result['message'] ?? 'Batch action failed.'
            ]
        ]);
    }
}
