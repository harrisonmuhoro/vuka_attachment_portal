<?php
/**
 * Cleanup path validator.
 *
 * Validates that a caller-supplied relative file path from the
 * submission_cleanup_queue is safe to delete.  Throws an
 * InvalidArgumentException describing the specific rejection reason so
 * callers can record it without exposing internal paths to end-users.
 *
 * Rules enforced:
 *  1. No null bytes.
 *  2. No directory-traversal sequences ("..").
 *  3. The resolved absolute path must start with the permitted upload dir.
 *
 * Physical file existence is NOT checked here; `realpath()` returns false
 * for missing files, which is treated as an out-of-bounds path.  Callers
 * should check `is_file()` after calling this function and treat a missing
 * file as already cleaned up (success).
 *
 * @param  string $relative  Relative path stored in submission_cleanup_queue.file_path.
 * @param  string $uploadDir Canonical, trailing-separator upload directory (e.g. UPLOAD_DIR).
 * @return string            The safe resolved absolute path.
 * @throws \InvalidArgumentException On any validation failure.
 */
function validateCleanupPath(string $relative, string $uploadDir): string
{
    // Rule 1: null bytes.
    if (str_contains($relative, "\0")) {
        throw new \InvalidArgumentException('Rejected: path contains null byte');
    }

    // Rule 2: traversal sequences.
    if (str_contains($relative, '..')) {
        throw new \InvalidArgumentException('Rejected: path contains traversal sequence');
    }

    // Build candidate absolute path; strip any leading separator so the join
    // is unambiguous regardless of how the path was stored.
    $uploadDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;
    $candidate = $uploadDir . ltrim($relative, '/\\');

    // Rule 3: canonical resolution must stay inside the upload directory.
    $resolved = realpath($candidate);
    if ($resolved === false || strncmp($resolved, $uploadDir, strlen($uploadDir)) !== 0) {
        // Do NOT include $candidate or $resolved in the exception message
        // so the message is safe to record in the database without leaking paths.
        throw new \InvalidArgumentException('Rejected: resolved path is outside permitted upload directory');
    }

    return $resolved;
}
