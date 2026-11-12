<?php
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SELECT id, file_path FROM submission_cleanup_queue WHERE completed_at IS NULL AND available_at <= NOW() ORDER BY id LIMIT 100");
$done = 0;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $path = UPLOAD_DIR . (string)$row['file_path'];
    $ok = !is_file($path) || @unlink($path);
    if ($ok) {
        $pdo->prepare('UPDATE submission_cleanup_queue SET completed_at = NOW(), attempts = attempts + 1, last_error = NULL WHERE id = ?')->execute([(int)$row['id']]);
        $done++;
    } else {
        $pdo->prepare("UPDATE submission_cleanup_queue SET attempts = attempts + 1, available_at = DATE_ADD(NOW(), INTERVAL LEAST(attempts + 1, 60) MINUTE), last_error = ? WHERE id = ?")
            ->execute(['Physical file deletion failed', (int)$row['id']]);
    }
}
fwrite(STDOUT, "Processed {$done} cleanup records.\n");
