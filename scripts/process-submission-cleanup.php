<?php
/**
 * Submission Cleanup Worker CLI Script
 * Vuka Attachment Portal — Phase 2.2
 *
 * Usage:
 *   php scripts/process-submission-cleanup.php [--limit=50]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script can only be executed from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/submission-cleanup-worker.php';

// Parse optional --limit argument
$limit = 50;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $val = (int)substr($arg, 8);
        if ($val > 0) {
            $limit = $val;
        }
    }
}

try {
    $worker = new SubmissionCleanupWorker($GLOBALS['pdo']);
    $stats = $worker->processBatch($limit);

    $claimed   = $stats['claimed'];
    $completed = $stats['completed'];
    $failed    = $stats['failed'];
    $terminal  = $stats['terminal'];

    fwrite(STDOUT, "Cleanup run finished: {$claimed} claimed, {$completed} completed, {$failed} retrying, {$terminal} terminal.\n");
    exit(0);
} catch (Throwable $e) {
    error_log('Cleanup worker top-level execution failure: ' . $e->getMessage());
    fwrite(STDERR, "Cleanup worker encountered an operational error.\n");
    exit(1);
}
