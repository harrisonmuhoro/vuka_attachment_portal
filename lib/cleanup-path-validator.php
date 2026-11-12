<?php
/**
 * Canonical Cleanup Path Validation & Normalization Library
 * Vuka Attachment Portal — Phase 2.2
 */

class CleanupPathValidator
{
    /**
     * Normalizes a raw document path to a canonical relative path for storage
     * in submission_cleanup_queue.
     *
     * Rules:
     * - Empty/whitespace path returns '' (represents BLOB-only storage).
     * - Rejects null bytes '\0' (throws InvalidArgumentException).
     * - Rejects directory traversal '..' (throws InvalidArgumentException).
     * - Rejects absolute filesystem paths (leading '/', drive letters like 'C:', UNC paths).
     * - Normalizes separators to forward slashes '/'.
     * - Removes redundant slashes and './'.
     * - Strips leading 'uploads/' prefix if present for canonical storage consistency.
     *
     * @param string $rawPath
     * @return string Normalized relative path, or '' if not a physical file.
     * @throws InvalidArgumentException If path contains prohibited sequences.
     */
    public static function normalizeForQueue(string $rawPath): string
    {
        $rawPath = trim($rawPath);
        if ($rawPath === '') {
            return '';
        }

        // Rule 1: Reject null bytes
        if (str_contains($rawPath, "\0")) {
            throw new InvalidArgumentException('Invalid path: contains null byte');
        }

        // Rule 2: Reject directory traversal
        if (str_contains($rawPath, '..')) {
            throw new InvalidArgumentException('Invalid path: contains directory traversal sequence');
        }

        // Rule 3: Reject absolute filesystem paths
        // Check Windows drive letter: C:, D:, etc.
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $rawPath)) {
            throw new InvalidArgumentException('Invalid path: absolute drive path not permitted');
        }
        // Check UNC path or leading slashes
        if (str_starts_with($rawPath, '\\\\') || str_starts_with($rawPath, '//') || str_starts_with($rawPath, '/') || str_starts_with($rawPath, '\\')) {
            throw new InvalidArgumentException('Invalid path: absolute path not permitted');
        }

        // Rule 4: Reject URI schemes (file://, phar://, http://, etc.)
        if (preg_match('/^[a-zA-Z0-9+.-]+:\/\//', $rawPath)) {
            throw new InvalidArgumentException('Invalid path: stream wrapper or URI scheme not permitted');
        }

        // Rule 5: Normalize directory separators to forward slash
        $normalized = str_replace('\\', '/', $rawPath);

        // Collapse multiple slashes
        $normalized = (string)preg_replace('#/+#', '/', $normalized);

        // Remove leading './'
        if (str_starts_with($normalized, './')) {
            $normalized = substr($normalized, 2);
        }

        // Strip leading 'uploads/' prefix if present for canonical relative path
        if (preg_match('#^uploads/(.*)$#i', $normalized, $matches)) {
            $normalized = $matches[1];
        }

        $normalized = trim($normalized, '/');

        // Check again after stripping
        if ($normalized === '' || str_contains($normalized, '..') || str_contains($normalized, "\0")) {
            return '';
        }

        return $normalized;
    }

    /**
     * Validates and resolves a stored cleanup path against the configured upload directory.
     *
     * Returns an array:
     * [
     *     'valid'         => bool,   // true if safe to process, false if malicious/invalid
     *     'resolved_path' => string, // absolute path if valid, empty string if invalid
     *     'exists'        => bool,   // true if physical file currently exists on disk
     *     'error'         => ?string // machine-safe error reason if invalid
     * ]
     *
     * @param string $storedPath Stored path from submission_cleanup_queue
     * @param string $uploadDir  Configured UPLOAD_DIR
     * @return array
     */
    public static function resolveAndValidate(string $storedPath, string $uploadDir): array
    {
        $storedPath = trim($storedPath);
        if ($storedPath === '') {
            return [
                'valid' => false,
                'resolved_path' => '',
                'exists' => false,
                'error' => 'Path is empty'
            ];
        }

        // 1. Basic security guards
        if (str_contains($storedPath, "\0")) {
            return [
                'valid' => false,
                'resolved_path' => '',
                'exists' => false,
                'error' => 'Path contains null byte'
            ];
        }

        if (str_contains($storedPath, '..')) {
            return [
                'valid' => false,
                'resolved_path' => '',
                'exists' => false,
                'error' => 'Path contains directory traversal'
            ];
        }

        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $storedPath) || str_starts_with($storedPath, '\\\\') || str_starts_with($storedPath, '//') || str_starts_with($storedPath, '/') || str_starts_with($storedPath, '\\')) {
            return [
                'valid' => false,
                'resolved_path' => '',
                'exists' => false,
                'error' => 'Absolute paths not permitted'
            ];
        }

        if (preg_match('/^[a-zA-Z0-9+.-]+:\/\//', $storedPath)) {
            return [
                'valid' => false,
                'resolved_path' => '',
                'exists' => false,
                'error' => 'URI scheme not permitted'
            ];
        }

        // 2. Validate and canonicalize upload directory
        $realUploadDir = realpath($uploadDir);
        if ($realUploadDir === false || !is_dir($realUploadDir)) {
            return [
                'valid' => false,
                'resolved_path' => '',
                'exists' => false,
                'error' => 'Configured upload directory does not exist'
            ];
        }
        $realUploadDir = rtrim($realUploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        // 3. Normalize relative path
        $cleanRelative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $storedPath);
        if (preg_match('/^uploads[\\\\\/](.*)$/i', $cleanRelative, $m)) {
            $candidateSub = $m[1];
        } else {
            $candidateSub = $cleanRelative;
        }

        $candidateA = $realUploadDir . ltrim($candidateSub, DIRECTORY_SEPARATOR);
        $candidateB = $realUploadDir . ltrim($cleanRelative, DIRECTORY_SEPARATOR);

        $targetCandidate = $candidateA;
        if (!file_exists($candidateA) && file_exists($candidateB)) {
            $targetCandidate = $candidateB;
        }

        // 4. Check if file exists and test symlink / realpath containment
        if (file_exists($targetCandidate) || is_link($targetCandidate)) {
            $canonicalTarget = realpath($targetCandidate);
            if ($canonicalTarget === false) {
                return [
                    'valid' => false,
                    'resolved_path' => '',
                    'exists' => false,
                    'error' => 'Could not canonicalize file target'
                ];
            }

            // Ensure canonical path is strictly inside $realUploadDir
            if (strncmp($canonicalTarget, $realUploadDir, strlen($realUploadDir)) !== 0) {
                return [
                    'valid' => false,
                    'resolved_path' => '',
                    'exists' => true,
                    'error' => 'Path or symlink escapes permitted upload directory'
                ];
            }

            return [
                'valid' => true,
                'resolved_path' => $canonicalTarget,
                'exists' => true,
                'error' => null
            ];
        }

        // 5. File does NOT exist on disk.
        // Verify that its parent directory stays inside $realUploadDir
        $parentDir = dirname($targetCandidate);
        $canonicalParent = realpath($parentDir);
        if ($canonicalParent !== false) {
            $canonicalParentWithSep = rtrim($canonicalParent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strncmp($canonicalParentWithSep, $realUploadDir, strlen($realUploadDir)) !== 0 && $canonicalParent !== rtrim($realUploadDir, DIRECTORY_SEPARATOR)) {
                return [
                    'valid' => false,
                    'resolved_path' => '',
                    'exists' => false,
                    'error' => 'Non-existent target path escapes upload directory'
                ];
            }
        }

        return [
            'valid' => true,
            'resolved_path' => $targetCandidate,
            'exists' => false,
            'error' => null
        ];
    }
}
