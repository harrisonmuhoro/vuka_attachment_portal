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

$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(100) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$files = glob(__DIR__ . '/../migrations/*.sql');
sort($files, SORT_STRING);
foreach ($files as $file) {
    $version = basename($file, '.sql');
    $stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
    $stmt->execute([$version]);
    if ($stmt->fetchColumn()) { fwrite(STDOUT, "Migration {$version} is already applied.\n"); continue; }
    $sql = file_get_contents($file);
    if ($sql === false) { fwrite(STDERR, "Migration file could not be read.\n"); exit(2); }
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)));
    try {
        foreach ($statements as $statement) if ($statement !== '') $pdo->exec($statement);
        $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
        fwrite(STDOUT, "Migration {$version} applied. Back up the database before running this command in production.\n");
    } catch (Throwable $e) {
        error_log("Migration {$version} failed: " . $e->getMessage());
        fwrite(STDERR, "Migration failed. Review database state and migration instructions; no destructive reset was run.\n");
        exit(1);
    }
}
