<?php
/**
 * Submission cleanup worker.
 * CLI only: php scripts/process-submission-cleanup.php
 *
 * Processes pending rows in submission_cleanup_queue:
 *   1. Claims a batch atomically with FOR UPDATE SKIP LOCKED so concurrent
 *      workers never process the same row.
 *   2. Pushes available_at forward during processing (claim window) so a
 *      crashed worker's rows become visible to peers after the window expires.
 *   3. Validates every file path before touching the filesystem.
 *   4. Keeps physical file deletion outside any database transaction.
 */
if (PHP_SAPI !== 'cli') exit(1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/cleanup-path-validator.php';

/** How long (minutes) to hold a claim; crashed workers' rows retry after this. */
const CLAIM_MINUTES = 10;
/** Maximum rows per worker invocation. */
const BATCH_SIZE = 50;

$uploadDir = rtrim((string) UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR;

// ── Phase 1: Atomically claim a batch ────────────────────────────────────────
// FOR UPDATE SKIP LOCKED prevents two concurrent workers from locking the same
// rows.  We push available_at forward inside the same transaction so a crashed
// worker's claim expires after CLAIM_MINUTES and becomes retryable.
$pdo->beginTransaction();
try {
    $claimSql = 'SELECT id FROM submission_cleanup_queue'
        . ' WHERE completed_at IS NULL AND available_at <= NOW()'
        . ' ORDER BY id'
        . ' LIMIT ' . BATCH_SIZE
        . ' FOR UPDATE SKIP LOCKED';
    $ids  = $pdo->query($claimSql)->fetchAll(PDO::FETCH_COLUMN);
    $rows = [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        // Fetch the full row data while still holding the lock.
        $rows = $pdo->prepare(
            "SELECT id, file_path FROM submission_cleanup_queue WHERE id IN ($placeholders)"
        );
        $rows->execute($ids);
        $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
        // Push available_at forward: other workers skip these rows until the claim expires.
        $pdo->prepare(
            "UPDATE submission_cleanup_queue"
            . " SET available_at = DATE_ADD(NOW(), INTERVAL " . CLAIM_MINUTES . " MINUTE)"
            . " WHERE id IN ($placeholders)"
        )->execute(array_merge($ids, $ids));
    }
    $pdo->commit();
} catch (Throwable $claimError) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Cleanup worker claim failed: ' . $claimError->getMessage() . PHP_EOL);
    exit(1);
}

if (empty($rows)) {
    fwrite(STDOUT, "No cleanup records pending.\n");
    exit(0);
}

// ── Phase 2: Process each claimed row (outside any transaction) ───────────────
$done = 0;
foreach ($rows as $row) {
    $rowId    = (int)$row['id'];
    $relative = (string)$row['file_path'];

    // Validate path; records a machine-safe reason on failure.
    try {
        $safePath = validateCleanupPath($relative, $uploadDir);
    } catch (\InvalidArgumentException $e) {
        $pdo->prepare(
            "UPDATE submission_cleanup_queue"
            . " SET attempts = attempts + 1,"
            . "     available_at = DATE_ADD(NOW(), INTERVAL LEAST(attempts + 1, 1440) MINUTE),"
            . "     last_error = ?"
            . " WHERE id = ?"
        )->execute([$e->getMessage(), $rowId]);
        error_log('Cleanup worker path validation failed for queue id ' . $rowId . ': ' . $e->getMessage());
        continue;
    }

    // Physical deletion is intentionally outside any DB transaction.
    $ok = !is_file($safePath) || @unlink($safePath);
    if ($ok) {
        $pdo->prepare(
            'UPDATE submission_cleanup_queue'
            . ' SET completed_at = NOW(), attempts = attempts + 1, last_error = NULL'
            . ' WHERE id = ?'
        )->execute([$rowId]);
        $done++;
    } else {
        $pdo->prepare(
            "UPDATE submission_cleanup_queue"
            . " SET attempts = attempts + 1,"
            . "     available_at = DATE_ADD(NOW(), INTERVAL LEAST(attempts + 1, 60) MINUTE),"
            . "     last_error = 'Physical file deletion failed'"
            . " WHERE id = ?"
        )->execute([$rowId]);
    }
}

fwrite(STDOUT, "Processed {$done} cleanup records.\n");
