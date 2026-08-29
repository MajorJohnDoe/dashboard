<?php
namespace Dashboard\Jobs;

use Dashboard\Core\User;
use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Jobs\JobApplication;

class JobController {
    private DatabaseInterface $db;
    private User $user;
    private JobApplication $jobModel;

    public function __construct(DatabaseInterface $db, User $user, ?JobApplication $jobModel = null) {
        $this->db = $db;
        $this->user = $user;
        $this->jobModel = $jobModel ?? new JobApplication($db);
    }

    /**
     * Get job applications list for the logged in user
     */
    public function handleGetApplications(array $filters = []): array {
        $userId = $this->user->getUserId();
        if (!$userId) {
            return [];
        }

        return $this->jobModel->getAllForUser($userId, $filters);
    }

    /**
     * Get single job application by ID
     */
    public function handleGetApplicationDetails(int $id): ?array {
        $userId = $this->user->getUserId();
        if (!$userId || $id <= 0) {
            return null;
        }

        return $this->jobModel->getById($id, $userId);
    }

    /**
     * Create new job application
     */
    public function handleCreateApplication(array $postData): array {
        $userId = $this->user->getUserId();
        if (!$userId) {
            return ['success' => false, 'message' => 'Unauthorized user session.'];
        }

        // Validate required fields
        $company = trim($postData['company'] ?? '');
        $position = trim($postData['position'] ?? '');

        if (empty($company) || empty($position)) {
            return ['success' => false, 'message' => 'Company name and position title are required.'];
        }

        $jobId = $this->jobModel->create($userId, $postData);
        if ($jobId) {
            return [
                'success' => true,
                'message' => 'Job application added successfully!',
                'job_id' => $jobId
            ];
        }

        return ['success' => false, 'message' => 'Failed to save job application to database.'];
    }

    /**
     * Update existing job application
     */
    public function handleUpdateApplication(int $id, array $postData): array {
        $userId = $this->user->getUserId();
        if (!$userId) {
            return ['success' => false, 'message' => 'Unauthorized user session.'];
        }

        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid application ID.'];
        }

        // Validate required fields
        $company = trim($postData['company'] ?? '');
        $position = trim($postData['position'] ?? '');

        if (empty($company) || empty($position)) {
            return ['success' => false, 'message' => 'Company name and position title are required.'];
        }

        $success = $this->jobModel->update($id, $userId, $postData);
        if ($success) {
            return [
                'success' => true,
                'message' => 'Application details updated successfully!',
                'job_id' => $id
            ];
        }

        return ['success' => false, 'message' => 'Failed to update job application.'];
    }

    /**
     * Delete existing job application
     */
    public function handleDeleteApplication(int $id): array {
        $userId = $this->user->getUserId();
        if (!$userId) {
            return ['success' => false, 'message' => 'Unauthorized user session.'];
        }

        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid application ID.'];
        }

        $success = $this->jobModel->delete($id, $userId);
        if ($success) {
            return [
                'success' => true,
                'message' => 'Job application deleted successfully!'
            ];
        }

        return ['success' => false, 'message' => 'Failed to delete job application.'];
    }

    /**
     * Process batch actions on multiple job applications
     */
    public function handleBatchAction(array $postData): array {
        $userId = $this->user->getUserId();
        if (!$userId) {
            return ['success' => false, 'message' => 'Unauthorized user session.'];
        }

        $ids = $postData['selected_jobs'] ?? ($postData['job_ids'] ?? []);
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        if (empty($ids) || !is_array($ids)) {
            return ['success' => false, 'message' => 'No applications selected.'];
        }

        $action = $postData['batch_action'] ?? ($postData['action'] ?? '');

        switch ($action) {
            case 'delete':
                $count = $this->jobModel->batchDelete($ids, $userId);
                return [
                    'success' => true,
                    'message' => "Successfully deleted {$count} application(s).",
                    'affected_rows' => $count
                ];

            case 'archive':
                $count = $this->jobModel->batchArchive($ids, $userId);
                return [
                    'success' => true,
                    'message' => "Successfully archived {$count} application(s).",
                    'affected_rows' => $count
                ];

            case 'status':
                $status = $postData['status'] ?? '';
                $allowed = [
                    JobApplication::STATUS_WISHLIST,
                    JobApplication::STATUS_APPLIED,
                    JobApplication::STATUS_INTERVIEWING,
                    JobApplication::STATUS_OFFER,
                    JobApplication::STATUS_REJECTED,
                    JobApplication::STATUS_ARCHIVED
                ];
                if (!in_array($status, $allowed, true)) {
                    return ['success' => false, 'message' => 'Invalid status selected for batch update.'];
                }

                $count = $this->jobModel->batchUpdateStatus($ids, $userId, $status);
                return [
                    'success' => true,
                    'message' => "Updated {$count} application(s) to " . ucfirst($status) . ".",
                    'affected_rows' => $count
                ];

            default:
                return ['success' => false, 'message' => 'Unknown batch action requested.'];
        }
    }

    /**
     * Get summary stats for the user
     */
    public function getSummaryStats(): array {
        $userId = $this->user->getUserId();
        if (!$userId) {
            return ['total' => 0, 'active' => 0, 'lia_count' => 0, 'interviewing_count' => 0];
        }

        return $this->jobModel->getSummaryStats($userId);
    }
}
