<?php
/**
 * Job Add / Edit Modal Dialog Partial
 * Follows Hyperboard's .modal-container, .dialog, and .nice-form-group conventions.
 */

use Dashboard\Core\CsrfProtection;
use Dashboard\Jobs\JobController;
use Dashboard\Jobs\JobApplication;

$jobController = new JobController($db, $user);

$action = $_GET['action'] ?? 'new';
$jobId = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$isEdit = ($action === 'edit' && $jobId > 0);

// Default blank application data
$jobData = [
    'id' => 0,
    'company' => '',
    'position' => '',
    'department' => '',
    'status' => JobApplication::STATUS_WISHLIST,
    'job_type' => JobApplication::TYPE_LIA,
    'work_model' => JobApplication::MODEL_REMOTE,
    'interest_level' => JobApplication::INTEREST_INTERESTED,
    'match_score' => '',
    'source' => '',
    'salary_range' => '',
    'notes' => '',
    'applied_date' => '',
    'deadline_date' => ''
];

// #MARK: DELETE job
// CSRF is enforced centrally by CsrfMiddleware via the Router
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || ($action === 'delete' && $jobId > 0)) {
    $result = $jobController->handleDeleteApplication($jobId);
    if ($result['success']) {
        triggerResponse([
            "refreshJobsList" => true,
            "refreshJobStats" => true,
            \Dashboard\Core\HtmxEvents::CLOSE_MODAL => true,
            \Dashboard\Core\HtmxEvents::GLOBAL_MESSAGE => [
                'type' => 'success',
                'message' => $result['message']
            ]
        ]);
    } else {
        triggerResponse([
            \Dashboard\Core\HtmxEvents::GLOBAL_MESSAGE => [
                'type' => 'error',
                'message' => $result['message']
            ]
        ]);
    }
}

// #MARK: POST create or edit
// CSRF is enforced centrally by CsrfMiddleware via the Router
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isEdit) {
        $result = $jobController->handleUpdateApplication($jobId, $_POST);
    } else {
        $result = $jobController->handleCreateApplication($_POST);
    }

    if ($result['success']) {
        triggerResponse([
            "refreshJobsList" => true,
            "refreshJobStats" => true,
            \Dashboard\Core\HtmxEvents::CLOSE_MODAL => true,
            \Dashboard\Core\HtmxEvents::GLOBAL_MESSAGE => [
                'type' => 'success',
                'message' => $result['message']
            ]
        ]);
    } else {
        triggerResponse([
            \Dashboard\Core\HtmxEvents::GLOBAL_MESSAGE => [
                'type' => 'error',
                'message' => $result['message']
            ]
        ]);
    }
}

// #MARK: GET load existing application
if ($isEdit) {
    $existing = $jobController->handleGetApplicationDetails($jobId);
    if ($existing) {
        $jobData = array_merge($jobData, $existing);
    }
}

$csrfToken = CsrfProtection::getToken();
?>

<div id="dialog-job-form" class="modal-container">
    <div class="dialog dialog-lg">
        <div class="dialog-header">
            <span><?= $isEdit ? 'Edit Job Application' : 'Add New Job Application' ?></span>
            <button class="close-modal-btn btn">X</button>
        </div>

        <div class="formOuter">
            <form id="form_jobApp" 
                  method="POST" 
                  hx-post="/jobs/dialog/<?= $isEdit ? 'edit/' . $jobId : 'new' ?>" 
                  hx-target="#dialog-job-form .formOuter"
                  hx-swap="beforeend">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="job_id" value="<?= $jobId ?>">
                <?php endif; ?>

                <div class="nice-form-group">
                    <div class="edit-grid">
                        <!-- Left Column: Title, Company, Department, Rich Text Notes -->
                        <div class="left-column">
                            <div class="flex-table">
                                <div class="flex-row">
                                    <div class="flex-cell flex-cell-50" style="padding-right: 0.5rem;">
                                        <label for="job_position">Position / Role:</label>
                                        <input type="text" 
                                               id="job_position" 
                                               name="position" 
                                               autocomplete="off" 
                                               autofocus 
                                               placeholder="e.g. Developer" 
                                               value="<?= htmlspecialchars($jobData['position']) ?>" 
                                               required>
                                    </div>
                                    <div class="flex-cell flex-cell-50">
                                        <label for="job_company">Company:</label>
                                        <input type="text" 
                                               id="job_company" 
                                               name="company" 
                                               autocomplete="off" 
                                               placeholder="e.g. Spotify" 
                                               value="<?= htmlspecialchars($jobData['company']) ?>" 
                                               required>
                                    </div>
                                </div>

                                <div class="flex-row">
                                    <div class="flex-cell flex-cell-50" style="padding-right: 0.5rem;">
                                        <label for="job_department">Department:</label>
                                        <input type="text" 
                                               id="job_department" 
                                               name="department" 
                                               placeholder="e.g. Core Engineering" 
                                               value="<?= htmlspecialchars($jobData['department'] ?? '') ?>">
                                    </div>
                                    <div class="flex-cell flex-cell-50">
                                        <label for="job_salary">Salary:</label>
                                        <input type="text" 
                                               id="job_salary" 
                                               name="salary_range" 
                                               placeholder="e.g. 35,000" 
                                               value="<?= htmlspecialchars($jobData['salary_range'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="flex-row">
                                    <div class="flex-cell">
                                        <label for="job_notes">Job description:</label>
                                        <textarea name="notes" 
                                                  id="job_notes" 
                                                  class="tinymce_editor tinymce-hidden" 
                                                  aria-hidden="true"><?= htmlspecialchars($jobData['notes'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Sidebar Metadata & Controls -->
                        <div class="right-column">
                            <div class="flex-table">
                                <!-- Status (2-Column Button Grid) -->
                                <div class="flex-row form-sidebar-section">
                                    <div class="flex-cell">
                                        <span class="form-label">Status</span>
                                        <div class="pill-select cols-2" style="margin-top: 0.3rem;">
                                            <input type="radio" id="status-wishlist" name="status" value="wishlist" <?= $jobData['status'] === 'wishlist' ? 'checked' : '' ?>>
                                            <label for="status-wishlist" class="pill-select-label status-btn-wishlist">Wishlist</label>

                                            <input type="radio" id="status-applied" name="status" value="applied" <?= $jobData['status'] === 'applied' ? 'checked' : '' ?>>
                                            <label for="status-applied" class="pill-select-label status-btn-applied">Applied</label>

                                            <input type="radio" id="status-interviewing" name="status" value="interviewing" <?= $jobData['status'] === 'interviewing' ? 'checked' : '' ?>>
                                            <label for="status-interviewing" class="pill-select-label status-btn-interviewing">Interview</label>

                                            <input type="radio" id="status-offer" name="status" value="offer" <?= $jobData['status'] === 'offer' ? 'checked' : '' ?>>
                                            <label for="status-offer" class="pill-select-label status-btn-offer">Offer</label>

                                            <input type="radio" id="status-rejected" name="status" value="rejected" <?= $jobData['status'] === 'rejected' ? 'checked' : '' ?>>
                                            <label for="status-rejected" class="pill-select-label status-btn-rejected">Rejected</label>

                                            <input type="radio" id="status-archived" name="status" value="archived" <?= $jobData['status'] === 'archived' ? 'checked' : '' ?>>
                                            <label for="status-archived" class="pill-select-label status-btn-archived">Archived</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Job Type (Includes LIA) -->
                                <div class="flex-row form-sidebar-section">
                                    <div class="flex-cell">
                                        <span class="form-label">Job Type</span>
                                        <select id="job_type" name="job_type" style="width: 100%; margin: 0.3rem 0 0 0;">
                                            <option value="lia" <?= $jobData['job_type'] === 'lia' ? 'selected' : '' ?>>LIA</option>
                                            <option value="full-time" <?= $jobData['job_type'] === 'full-time' ? 'selected' : '' ?>>Full-Time</option>
                                            <option value="part-time" <?= $jobData['job_type'] === 'part-time' ? 'selected' : '' ?>>Part-Time</option>
                                            <option value="contract" <?= $jobData['job_type'] === 'contract' ? 'selected' : '' ?>>Contract</option>
                                            <option value="internship" <?= $jobData['job_type'] === 'internship' ? 'selected' : '' ?>>Internship</option>
                                            <option value="thesis" <?= $jobData['job_type'] === 'thesis' ? 'selected' : '' ?>>Thesis / Exjobb</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Work Model -->
                                <div class="flex-row form-sidebar-section">
                                    <div class="flex-cell">
                                        <span class="form-label">Work Model</span>
                                        <select id="work_model" name="work_model" style="width: 100%; margin: 0.3rem 0 0 0;">
                                            <option value="remote" <?= $jobData['work_model'] === 'remote' ? 'selected' : '' ?>>Remote</option>
                                            <option value="hybrid" <?= $jobData['work_model'] === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
                                            <option value="onsite" <?= $jobData['work_model'] === 'onsite' ? 'selected' : '' ?>>On-Site</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Interest (3 Options: Excited = Green, Interested = Blue, Meh = Yellow) -->
                                <div class="flex-row form-sidebar-section">
                                    <div class="flex-cell">
                                        <span class="form-label">Interest</span>
                                        <div class="pill-select" style="margin-top: 0.3rem;">
                                            <input type="radio" id="interest-excited" name="interest_level" value="excited" <?= ($jobData['interest_level'] === 'excited' || $jobData['interest_level'] === 'dream') ? 'checked' : '' ?>>
                                            <label for="interest-excited" class="pill-select-label interest-excited">Excited</label>

                                            <input type="radio" id="interest-interested" name="interest_level" value="interested" <?= ($jobData['interest_level'] === 'interested' || empty($jobData['interest_level'])) ? 'checked' : '' ?>>
                                            <label for="interest-interested" class="pill-select-label interest-interested">Interested</label>

                                            <input type="radio" id="interest-meh" name="interest_level" value="meh" <?= $jobData['interest_level'] === 'meh' ? 'checked' : '' ?>>
                                            <label for="interest-meh" class="pill-select-label interest-meh">Meh</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Source -->
                                <div class="flex-row form-sidebar-section">
                                    <div class="flex-cell">
                                        <span class="form-label">Source</span>
                                        <input type="text" 
                                               id="job_source" 
                                               name="source" 
                                               placeholder="LinkedIn, Referral..." 
                                               style="margin-top: 0.3rem;"
                                               value="<?= htmlspecialchars($jobData['source'] ?? '') ?>">
                                    </div>
                                </div>

                                <!-- Applied Date & Deadline -->
                                <div class="flex-row form-sidebar-section">
                                    <div class="flex-cell flex-cell-50" style="padding-right: 0.3rem;">
                                        <span class="form-label">Applied</span>
                                        <input type="date" 
                                               id="job_applied_date" 
                                               name="applied_date" 
                                               style="margin-top: 0.3rem;"
                                               value="<?= htmlspecialchars($jobData['applied_date'] ?? '') ?>">
                                    </div>
                                    <div class="flex-cell flex-cell-50">
                                        <span class="form-label">Deadline</span>
                                        <input type="date" 
                                               id="job_deadline" 
                                               name="deadline_date" 
                                               style="margin-top: 0.3rem;"
                                               value="<?= htmlspecialchars($jobData['deadline_date'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions Footer (matching Task dialog) -->
                        <div class="form-actions">
                            <div class="flex-table">
                                <div class="flex-row" style="align-items: center;">
                                    <div class="flex-cell">
                                        <?php if ($isEdit): ?>
                                            <button type="button" 
                                                    class="btn btn-light-gray btn-hover-red" 
                                                    tabindex="-1"
                                                    hx-delete="/jobs/dialog/delete/<?= $jobId ?>"
                                                    hx-confirm="Are you sure you want to delete this job application?"
                                                    hx-headers='{"X-CSRF-Token": "<?= htmlspecialchars($csrfToken) ?>"}'
                                                    hx-target="#dialog-job-form .formOuter"
                                                    hx-swap="beforeend">
                                                Delete application
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-cell flex-vertical-center flex-right" style="text-align: right; margin-left: auto;">
                                        <input type="submit" 
                                               value="<?= $isEdit ? 'Save application' : 'Add application' ?>" 
                                               form="form_jobApp" 
                                               class="btn btn-green">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
