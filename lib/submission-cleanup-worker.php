<?php
/**
 * Reliable Submission Cleanup Worker Service
 * Vuka Attachment Portal — Phase 2.2
 */

require_once __DIR__ . '/cleanup-path-validator.php';

class SubmissionCleanupWorker
{
    private PDO $pdo;
    private int $maxAttempts;
    private int $claimMinutes;

    public function __construct(PDO $pdo, int $maxAttempts = 5, int $claimMinutes = 10)
    {
        $this->pdo = $pdo;
        $this->maxAttempts = $maxAttempts;
        $this->claimMinutes = $claimMinutes;
    }

    /**
     * Claims and processes a batch of eligible cleanup records.
     *
     * @param int $limit Maximum records to claim and process
     * @param string|null $uploadDir Override upload directory (defaults to UPLOAD_DIR constant)
     * @return array Processing summary statistics
     */
    public function processBatch(int $limit = 50, ?string $uploadDir = null): array
    {
        if ($uploadDir === null) {
            $uploadDir = defined('UPLOAD_DIR') ? (string)UPLOAD_DIR : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        }

        $stats = [
            'claimed' => 0,
            'completed' => 0,
            'missing_treated_completed' => 0,
            'failed' => 0,
            'terminal' => 0,
        ];

        $isMysql = ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql');
        $lockClause = $isMysql ? ' FOR UPDATE SKIP LOCKED' : '';

        // Phase 1: Atomically claim pending eligible records
        $claimedRows = [];
        try {
            $this->pdo->beginTransaction();
            $claimSql = 'SELECT id, submission_id, file_path, attempts'
                . ' FROM submission_cleanup_queue'
                . ' WHERE completed_at IS NULL'
                . '   AND attempts < ?'
                . '   AND available_at <= NOW()'
                . ' ORDER BY id ASC'
                . ' LIMIT ' . (int)$limit
                . $lockClause;

            $stmt = $this->pdo->prepare($claimSql);
            $stmt->execute([$this->maxAttempts]);
            $claimedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($claimedRows)) {
                $ids = array_column($claimedRows, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $leaseExpires = date('Y-m-d H:i:s', time() + ($this->claimMinutes * 60));

                $updateSql = "UPDATE submission_cleanup_queue SET available_at = ? WHERE id IN ($placeholders)";
                $params = array_merge([$leaseExpires], $ids);
                $this->pdo->prepare($updateSql)->execute($params);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Cleanup worker batch claim failed: ' . $e->getMessage());
            throw new RuntimeException('Database claim operation failed', 0, $e);
        }

        $stats['claimed'] = count($claimedRows);
        if ($stats['claimed'] === 0) {
            return $stats;
        }

        // Phase 2: Process each claimed row individually outside any database transaction
        foreach ($claimedRows as $row) {
            $rowId = (int)$row['id'];
            $currentAttempts = (int)$row['attempts'];
            $storedPath = (string)$row['file_path'];

            $res = CleanupPathValidator::resolveAndValidate($storedPath, $uploadDir);

            // Case A: Path validation failed (e.g. traversal, null bytes, escaped boundary)
            if (!$res['valid']) {
                $newAttempts = $currentAttempts + 1;
                $isTerminal = ($newAttempts >= $this->maxAttempts);
                $safeError = 'Path rejected: ' . ($res['error'] ?? 'invalid path');
                if ($isTerminal) {
                    $safeError = 'Terminal failure (max retries reached): ' . $safeError;
                    $stats['terminal']++;
                } else {
                    $stats['failed']++;
                }
                // Backoff: 2^attempts minutes (max 1440 min = 24h)
                $backoffMin = min((int)pow(2, $newAttempts), 1440);
                $nextAvailable = date('Y-m-d H:i:s', time() + ($backoffMin * 60));

                $stmt = $this->pdo->prepare(
                    'UPDATE submission_cleanup_queue SET attempts = ?, available_at = ?, last_error = ? WHERE id = ?'
                );
                $stmt->execute([$newAttempts, $nextAvailable, $safeError, $rowId]);
                error_log("Cleanup record {$rowId} validation failure: {$safeError}");
                continue;
            }

            // Case B: Valid path and physical file does NOT exist -> treat as successfully cleaned
            if (!$res['exists']) {
                $stmt = $this->pdo->prepare(
                    'UPDATE submission_cleanup_queue SET completed_at = NOW(), last_error = NULL WHERE id = ?'
                );
                $stmt->execute([$rowId]);
                $stats['completed']++;
                $stats['missing_treated_completed']++;
                continue;
            }

            // Case C: Valid path and physical file exists -> unlink outside transaction
            $unlinkOk = @unlink($res['resolved_path']);
            if ($unlinkOk) {
                $stmt = $this->pdo->prepare(
                    'UPDATE submission_cleanup_queue SET completed_at = NOW(), last_error = NULL WHERE id = ?'
                );
                $stmt->execute([$rowId]);
                $stats['completed']++;
            } else {
                $newAttempts = $currentAttempts + 1;
                $isTerminal = ($newAttempts >= $this->maxAttempts);
                $safeError = 'Physical file deletion failed';
                if ($isTerminal) {
                    $safeError = 'Terminal failure: physical file deletion failed after max retries';
                    $stats['terminal']++;
                } else {
                    $stats['failed']++;
                }

                $backoffMin = min((int)pow(2, $newAttempts), 60);
                $nextAvailable = date('Y-m-d H:i:s', time() + ($backoffMin * 60));

                $stmt = $this->pdo->prepare(
                    'UPDATE submission_cleanup_queue SET attempts = ?, available_at = ?, last_error = ? WHERE id = ?'
                );
                $stmt->execute([$newAttempts, $nextAvailable, $safeError, $rowId]);
                error_log("Cleanup record {$rowId} physical deletion failed (attempts={$newAttempts})");
            }
        }

        return $stats;
    }
}
