<?php
/**
 * Jobs Table Partial
 * Renders the tabular list of job applications with bulk checkboxes, status pills, and action hooks.
 */

use Dashboard\Core\CsrfProtection;
use Dashboard\Jobs\JobController;

$jobController = new JobController($db, $user);
$jobs = $jobController->handleGetApplications($_GET);
$csrfToken = CsrfProtection::getToken();
?>

<div class="data-table-card">
    <div class="data-table-responsive">
        <table class="data-table" id="jobs-data-table" data-selectable-table>
            <thead>
                <tr>
                    <th style="width: 36px; text-align: center;">
                        <input type="checkbox" data-table-select-all class="job-checkbox" title="Select all applications">
                    </th>
                    <th class="sortable">Company</th>
                    <th class="sortable">Position / Role</th>
                    <th class="sortable">Status</th>
                    <th>Type & Model</th>
                    <th class="sortable">Interest</th>
                    <th>Source</th>
                    <th class="sortable">Applied / Deadline</th>
                    <th>Stage Time</th>
                    <th style="width: 70px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($jobs)): ?>
                    <?php foreach ($jobs as $job): 
                        $appliedDateDisplay = !empty($job['applied_date']) ? htmlspecialchars($job['applied_date']) : 'Not applied';
                        $deadlineDisplay = !empty($job['deadline_date']) ? 'Due: ' . htmlspecialchars($job['deadline_date']) : 'No deadline';
                        $interest = $job['interest_level'] ?? ($job['interest'] ?? 'interested');
                        $interestClass = match($interest) {
                            'excited', 'dream' => 'interest-excited',
                            'meh' => 'interest-meh',
                            default => 'interest-interested'
                        };
                        $interestLabel = match($interest) {
                            'excited', 'dream' => 'Excited',
                            'meh' => 'Meh',
                            default => 'Interested'
                        };
                    ?>
                    <tr data-filter-row 
                        data-status="<?= htmlspecialchars($job['status']) ?>"
                        data-type="<?= htmlspecialchars($job['job_type']) ?>"
                        data-work-model="<?= htmlspecialchars($job['work_model']) ?>"
                        data-stale="<?= $job['is_stale'] ? 'true' : 'false' ?>">
                        
                        <td style="text-align: center;">
                            <input type="checkbox" 
                                   name="selected_jobs[]" 
                                   value="<?= $job['id'] ?>" 
                                   data-row-checkbox
                                   class="job-checkbox job-row-checkbox"
                                   title="Select this row">
                        </td>

                        <!-- Company -->
                        <td>
                            <div class="company-cell">
                                <div class="company-logo-badge">
                                    <?= htmlspecialchars($job['company_initial']) ?>
                                </div>
                                <div>
                                    <div class="company-name">
                                        <a href="javascript:void(0)" 
                                           class="company-link open-modal-btn" 
                                           title="View / Edit application"
                                           data-modal-target="#dialog-job-form"
                                           hx-get="/jobs/dialog/edit/<?= $job['id'] ?>"
                                           hx-target="body"
                                           hx-swap="beforeend">
                                            <?= htmlspecialchars($job['company']) ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Position -->
                        <td>
                            <div class="position-title"><?= htmlspecialchars($job['position']) ?></div>
                            <?php if (!empty($job['department'])): ?>
                                <div class="position-sub"><?= htmlspecialchars($job['department']) ?></div>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td>
                            <span class="pill status-btn-<?= htmlspecialchars($job['status']) ?>">
                                <?= htmlspecialchars(ucfirst($job['status'])) ?>
                            </span>
                        </td>

                        <!-- Type & Model Badges (Using Core label-badge) -->
                        <td>
                            <?php if ($job['job_type'] === 'lia'): ?>
                                <span class="label-badge label-lia" title="Lärande i arbete">LIA</span>
                            <?php else: ?>
                                <span class="label-badge label-<?= htmlspecialchars($job['job_type']) ?>"><?= htmlspecialchars(ucfirst($job['job_type'])) ?></span>
                            <?php endif; ?>

                            <span class="label-badge label-<?= htmlspecialchars($job['work_model']) ?>">
                                <?= htmlspecialchars(ucfirst($job['work_model'])) ?>
                            </span>
                        </td>

                        <!-- Interest Badge -->
                        <td>
                            <span class="pill <?= $interestClass ?>">
                                <?= $interestLabel ?>
                            </span>
                        </td>

                        <!-- Source -->
                        <td>
                            <span class="source-tag">
                                <?= htmlspecialchars($job['source'] ?: '—') ?>
                            </span>
                        </td>

                        <!-- Applied Date / Deadline -->
                        <td>
                            <div style="color: #e5edf8;"><?= $appliedDateDisplay ?></div>
                            <div style="font-size: 0.74rem; color: #8da4be;"><?= $deadlineDisplay ?></div>
                        </td>

                        <!-- Stage Time / Stale -->
                        <td>
                            <?php if ($job['is_stale']): ?>
                                <span class="time-badge stale" title="Stale application - 14+ days without status update">
                                    ⚠ <?= $job['days_in_stage'] ?>d stale
                                </span>
                            <?php else: ?>
                                <span class="time-badge">
                                    <?= $job['days_in_stage'] ?>d
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td style="text-align: right;">
                            <div class="data-table-actions" style="justify-content: flex-end;">
                                <button class="btn-icon open-modal-btn" 
                                        title="Edit application"
                                        data-modal-target="#dialog-job-form"
                                        hx-get="/jobs/dialog/edit/<?= $job['id'] ?>"
                                        hx-target="body"
                                        hx-swap="beforeend">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                                <button class="btn-icon btn-icon-danger" 
                                        title="Delete application"
                                        hx-delete="/jobs/dialog/delete/<?= $job['id'] ?>"
                                        hx-confirm="Delete application for <?= htmlspecialchars(addslashes($job['company'])) ?>?"
                                        hx-headers='{"X-CSRF-Token": "<?= htmlspecialchars($csrfToken) ?>"}'
                                        hx-swap="none">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr data-initial-empty>
                        <td colspan="10">
                            <div class="data-table-empty">
                                <h3>No job applications yet</h3>
                                <p>Track your job and internship search by clicking <strong>+ Add Application</strong> above.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <tr data-filter-empty style="display: none;">
                    <td colspan="10">
                        <div class="data-table-empty">
                            <h3>No matching applications found</h3>
                            <p>Try adjusting your search filters or status selection.</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
