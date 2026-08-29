<?php
/**
 * Job Stats Badges Partial
 */

use Dashboard\Jobs\JobController;

$jobController = new JobController($db, $user);
$stats = $jobController->getSummaryStats();
?>
<div id="jobs-stats-badges" 
     class="jobs-stats-summary"
     hx-get="/jobs/stats"
     hx-trigger="refreshJobStats from:body"
     hx-swap="outerHTML">
    <span class="stat-badge active"><strong><?= $stats['total'] ?></strong> Total</span>
    <span class="stat-badge"><strong><?= $stats['active'] ?></strong> Active</span>
    <span class="stat-badge"><strong><?= $stats['lia_count'] ?></strong> LIA</span>
    <span class="stat-badge"><strong><?= $stats['interviewing_count'] ?></strong> Interviews</span>
</div>
