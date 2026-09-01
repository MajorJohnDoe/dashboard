<?php
namespace Dashboard\Jobs;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\ItemImageService;

class JobApplication {
    private DatabaseInterface $db;

    public const STATUS_WISHLIST = 'wishlist';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_INTERVIEWING = 'interviewing';
    public const STATUS_OFFER = 'offer';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

    public const TYPE_LIA = 'lia';
    public const TYPE_FULL_TIME = 'full-time';
    public const TYPE_PART_TIME = 'part-time';
    public const TYPE_CONTRACT = 'contract';
    public const TYPE_INTERNSHIP = 'internship';
    public const TYPE_THESIS = 'thesis';

    public const MODEL_REMOTE = 'remote';
    public const MODEL_HYBRID = 'hybrid';
    public const MODEL_ONSITE = 'onsite';

    public const INTEREST_DREAM = 'dream';
    public const INTEREST_EXCITED = 'excited';
    public const INTEREST_INTERESTED = 'interested';
    public const INTEREST_MEH = 'meh';

    public function __construct(DatabaseInterface $db) {
        $this->db = $db;
    }

    private function getImageService(): ItemImageService {
        return new ItemImageService($this->db);
    }

    /**
     * Create a new job application record
     */
    public function create(int $userId, array $data): int|false {
        $company = trim($data['company'] ?? '');
        $position = trim($data['position'] ?? '');
        $department = !empty($data['department']) ? trim($data['department']) : null;
        $status = $data['status'] ?? self::STATUS_WISHLIST;
        $jobType = $data['job_type'] ?? self::TYPE_LIA;
        $workModel = $data['work_model'] ?? self::MODEL_REMOTE;
        $interestLevel = $data['interest_level'] ?? ($data['interest'] ?? self::INTEREST_INTERESTED);
        $matchScore = isset($data['match_score']) && $data['match_score'] !== '' ? intval($data['match_score']) : null;
        $source = !empty($data['source']) ? trim($data['source']) : null;
        $salaryRange = !empty($data['salary_range']) ? trim($data['salary_range']) : null;
        $notes = !empty($data['notes']) ? $data['notes'] : null;
        $appliedDate = !empty($data['applied_date']) ? $data['applied_date'] : null;
        $deadlineDate = !empty($data['deadline_date']) ? $data['deadline_date'] : null;

        // Auto-set applied date if status is applied/interviewing/offer and no date provided
        if (!$appliedDate && in_array($status, [self::STATUS_APPLIED, self::STATUS_INTERVIEWING, self::STATUS_OFFER])) {
            $appliedDate = date('Y-m-d');
        }

        $query = "INSERT INTO job_applications (
                    user_id, company, position, department, status, job_type, work_model,
                    interest_level, match_score, source, salary_range, notes, applied_date,
                    deadline_date, status_updated_at, created_at, updated_at
                  ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW()
                  )";

        $this->db->beginTransaction();
        try {
            $result = $this->db->q(
                $query,
                "isssssssisssss",
                $userId,
                $company,
                $position,
                $department,
                $status,
                $jobType,
                $workModel,
                $interestLevel,
                $matchScore,
                $source,
                $salaryRange,
                $notes,
                $appliedDate,
                $deadlineDate
            );

            if ($result === false) {
                throw new \RuntimeException('Failed to insert job application.');
            }

            $jobId = (int)$this->db->lastInsertId();

            // Convert pasted base64 images to files and persist the rewritten notes
            if ($notes !== null) {
                $processedNotes = $this->getImageService()->processAndPersist($userId, $jobId, $notes, 'job');
                if ($processedNotes !== $notes) {
                    $updateResult = $this->db->q(
                        "UPDATE job_applications SET notes = ? WHERE id = ? AND user_id = ?",
                        "sii",
                        $processedNotes,
                        $jobId,
                        $userId
                    );
                    if ($updateResult === false) {
                        throw new \RuntimeException('Failed to update notes after image processing.');
                    }
                }
            }

            $this->db->commit();
            return $jobId;
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Error creating job application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing job application
     */
    public function update(int $id, int $userId, array $data): bool {
        $existing = $this->getById($id, $userId);
        if (!$existing) {
            return false;
        }

        $company = trim($data['company'] ?? $existing['company']);
        $position = trim($data['position'] ?? $existing['position']);
        $department = isset($data['department']) ? (!empty($data['department']) ? trim($data['department']) : null) : $existing['department'];
        $status = $data['status'] ?? $existing['status'];
        $jobType = $data['job_type'] ?? $existing['job_type'];
        $workModel = $data['work_model'] ?? $existing['work_model'];
        $interestLevel = $data['interest_level'] ?? ($data['interest'] ?? $existing['interest_level']);
        $matchScore = isset($data['match_score']) ? ($data['match_score'] !== '' ? intval($data['match_score']) : null) : $existing['match_score'];
        $source = isset($data['source']) ? (!empty($data['source']) ? trim($data['source']) : null) : $existing['source'];
        $salaryRange = isset($data['salary_range']) ? (!empty($data['salary_range']) ? trim($data['salary_range']) : null) : $existing['salary_range'];
        $notes = isset($data['notes']) ? $data['notes'] : $existing['notes'];
        $appliedDate = isset($data['applied_date']) ? (!empty($data['applied_date']) ? $data['applied_date'] : null) : $existing['applied_date'];
        $deadlineDate = isset($data['deadline_date']) ? (!empty($data['deadline_date']) ? $data['deadline_date'] : null) : $existing['deadline_date'];

        // If status has changed, update status_updated_at timestamp
        $statusChanged = ($status !== $existing['status']);
        $statusUpdateClause = $statusChanged ? ", status_updated_at = NOW()" : "";

        $query = "UPDATE job_applications SET
                    company = ?,
                    position = ?,
                    department = ?,
                    status = ?,
                    job_type = ?,
                    work_model = ?,
                    interest_level = ?,
                    match_score = ?,
                    source = ?,
                    salary_range = ?,
                    notes = ?,
                    applied_date = ?,
                    deadline_date = ?,
                    updated_at = NOW()
                    {$statusUpdateClause}
                  WHERE id = ? AND user_id = ?";

        $this->db->beginTransaction();
        try {
            // Convert pasted base64 images to files and remove orphaned ones before persisting
            if ($notes !== null) {
                $notes = $this->getImageService()->processAndPersist($userId, $id, $notes, 'job');
            }

            $result = $this->db->q(
                $query,
                "sssssssisssssii",
                $company,
                $position,
                $department,
                $status,
                $jobType,
                $workModel,
                $interestLevel,
                $matchScore,
                $source,
                $salaryRange,
                $notes,
                $appliedDate,
                $deadlineDate,
                $id,
                $userId
            );

            if ($result === false) {
                throw new \RuntimeException('Failed to update job application.');
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Error updating job application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a single job application
     */
    public function delete(int $id, int $userId): bool {
        $this->db->beginTransaction();
        try {
            // Remove embedded images (files + shared_item_images rows) before deleting the row
            $this->getImageService()->deleteAllForItem($userId, $id, 'job');

            $query = "DELETE FROM job_applications WHERE id = ? AND user_id = ?";
            $result = $this->db->q($query, "ii", $id, $userId);

            if ($result === false) {
                throw new \RuntimeException('Failed to delete job application.');
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Error deleting job application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete multiple job applications
     */
    public function batchDelete(array $ids, int $userId): int {
        $cleanIds = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
        if (empty($cleanIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $types = str_repeat('i', count($cleanIds)) . 'i';
        $params = array_merge($cleanIds, [$userId]);

        $this->db->beginTransaction();
        try {
            // Remove embedded images (files + shared_item_images rows) before deleting the rows
            $this->getImageService()->deleteAllForItems($userId, $cleanIds, 'job');

            $query = "DELETE FROM job_applications WHERE id IN ($placeholders) AND user_id = ?";
            $result = $this->db->q($query, $types, ...$params);

            if ($result === false) {
                throw new \RuntimeException('Failed to batch delete job applications.');
            }

            $this->db->commit();
            return is_numeric($result) ? (int)$result : 0;
        } catch (\Exception $e) {
            $this->db->rollback();
            error_log("Error batch deleting job applications: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Batch update application status
     */
    public function batchUpdateStatus(array $ids, int $userId, string $status): int {
        $cleanIds = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
        if (empty($cleanIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $types = 's' . str_repeat('i', count($cleanIds)) . 'i';
        $params = array_merge([$status], $cleanIds, [$userId]);

        $query = "UPDATE job_applications 
                  SET status = ?, status_updated_at = NOW(), updated_at = NOW() 
                  WHERE id IN ($placeholders) AND user_id = ?";
        $result = $this->db->q($query, $types, ...$params);

        return is_numeric($result) ? (int)$result : 0;
    }

    /**
     * Batch archive applications
     */
    public function batchArchive(array $ids, int $userId): int {
        return $this->batchUpdateStatus($ids, $userId, self::STATUS_ARCHIVED);
    }

    /**
     * Get a single job application by ID
     */
    public function getById(int $id, int $userId): ?array {
        $query = "SELECT ja.*, 
                         DATEDIFF(NOW(), ja.status_updated_at) as days_in_stage
                  FROM job_applications ja
                  WHERE ja.id = ? AND ja.user_id = ?
                  LIMIT 1";

        $result = $this->db->q($query, "ii", $id, $userId);
        if (!empty($result) && is_array($result)) {
            return $this->augmentRecord($result[0]);
        }
        return null;
    }

    /**
     * Get all job applications for user with optional filters
     */
    public function getAllForUser(int $userId, array $filters = []): array {
        $conditions = ["ja.user_id = ?"];
        $params = [$userId];
        $types = "i";

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $conditions[] = "ja.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }

        // Job type or work model filter
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $typeVal = $filters['type'];
            if (in_array($typeVal, [self::MODEL_REMOTE, self::MODEL_HYBRID, self::MODEL_ONSITE])) {
                $conditions[] = "ja.work_model = ?";
                $params[] = $typeVal;
                $types .= "s";
            } else {
                $conditions[] = "ja.job_type = ?";
                $params[] = $typeVal;
                $types .= "s";
            }
        }

        // Search query filter (company, position, department, notes, source)
        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim($filters['search']) . '%';
            $conditions[] = "(ja.company LIKE ? OR ja.position LIKE ? OR ja.department LIKE ? OR ja.notes LIKE ? OR ja.source LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sssss";
        }

        $whereSql = implode(" AND ", $conditions);

        $query = "SELECT ja.*, 
                         DATEDIFF(NOW(), ja.status_updated_at) as days_in_stage
                  FROM job_applications ja
                  WHERE {$whereSql}
                  ORDER BY ja.status = 'offer' DESC, 
                           ja.status = 'interviewing' DESC, 
                           ja.status = 'applied' DESC, 
                           ja.status = 'wishlist' DESC, 
                           ja.updated_at DESC";

        $results = $this->db->q($query, $types, ...$params);

        if (!is_array($results)) {
            return [];
        }

        $augmented = array_map([$this, 'augmentRecord'], $results);

        // Stale filter (14+ days in active stages)
        if (!empty($filters['stale']) && ($filters['stale'] === 'true' || $filters['stale'] === '1' || $filters['stale'] === 1)) {
            $augmented = array_values(array_filter($augmented, fn($job) => $job['is_stale']));
        }

        return $augmented;
    }

    /**
     * Get summary metrics for header stats pills
     */
    public function getSummaryStats(int $userId): array {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('wishlist', 'applied', 'interviewing', 'offer') THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN job_type = 'lia' THEN 1 ELSE 0 END) as lia_count,
                    SUM(CASE WHEN status = 'interviewing' THEN 1 ELSE 0 END) as interviewing_count
                  FROM job_applications
                  WHERE user_id = ?";

        $result = $this->db->q($query, "i", $userId);

        if (!empty($result) && is_array($result)) {
            $row = $result[0];
            return [
                'total' => (int)($row['total'] ?? 0),
                'active' => (int)($row['active'] ?? 0),
                'lia_count' => (int)($row['lia_count'] ?? 0),
                'interviewing_count' => (int)($row['interviewing_count'] ?? 0)
            ];
        }

        return [
            'total' => 0,
            'active' => 0,
            'lia_count' => 0,
            'interviewing_count' => 0
        ];
    }

    /**
     * Augment raw database record with UI-friendly computed properties
     */
    private function augmentRecord(array $row): array {
        $company = trim($row['company'] ?? '');
        $row['company_initial'] = !empty($company) ? mb_strtoupper(mb_substr($company, 0, 1, 'UTF-8')) : '?';
        $row['days_in_stage'] = max(0, (int)($row['days_in_stage'] ?? 0));
        
        // Stale logic: 14+ days in active stages (wishlist, applied, interviewing)
        $activeStatuses = [self::STATUS_WISHLIST, self::STATUS_APPLIED, self::STATUS_INTERVIEWING];
        $row['is_stale'] = ($row['days_in_stage'] >= 14 && in_array($row['status'], $activeStatuses));

        // Format interest label
        $interest = $row['interest_level'] ?? self::INTEREST_INTERESTED;
        $row['interest'] = $interest;
        $row['interest_label'] = match($interest) {
            self::INTEREST_DREAM => '🔥 Dream',
            self::INTEREST_EXCITED => '⚡ Excited',
            self::INTEREST_INTERESTED => '👍 Interested',
            self::INTEREST_MEH => '😐 Meh',
            default => '👍 Interested'
        };

        // Score fallback
        $row['score'] = isset($row['match_score']) ? (int)$row['match_score'] : ($interest === self::INTEREST_DREAM ? 95 : 75);

        return $row;
    }
}
