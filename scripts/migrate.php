<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "This migration requires MySQL/MariaDB.\n");
    exit(2);
}

$version = '001_phase1_security';
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
$stmt->execute([$version]);
if ($stmt->fetchColumn()) {
    fwrite(STDOUT, "Migration {$version} is already applied.\n");
    exit(0);
}

$sql = file_get_contents(__DIR__ . '/../migrations/' . $version . '.sql');
if ($sql === false) {
    fwrite(STDERR, "Migration file could not be read.\n");
    exit(2);
}
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)));
try {
    foreach ($statements as $statement) {
        if ($statement === '') continue;
        $pdo->exec($statement);
    }
    $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
    fwrite(STDOUT, "Migration {$version} applied. Back up the database before running this command in production.\n");
} catch (Throwable $e) {
    error_log('Phase 1 migration failed.');
    fwrite(STDERR, "Migration failed. Review database state and migration instructions; no destructive reset was run.\n");
    exit(1);
}
