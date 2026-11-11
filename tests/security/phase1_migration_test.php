<?php
/** Exercise Phase 1 migration and guarded rollback in a freshly created MySQL database. */
error_reporting(E_ALL);
require_once __DIR__ . '/../../config/config.php';

if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "FAIL Migration integration requires the configured MySQL/MariaDB connection.\n");
    exit(2);
}
$databaseName = 'vuka_phase1_migration_test_' . bin2hex(random_bytes(6));
if (!preg_match('/^vuka_phase1_migration_test_[a-f0-9]{12}$/', $databaseName)) exit(2);
$created = false;
$tests = [];
$record = static function (string $name, bool $ok, string $detail = '') use (&$tests) {
    $tests[] = [$name, $ok, $detail];
};

try {
    $pdo->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $testDsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . $databaseName . ';charset=utf8mb4';
    $testDb = new PDO($testDsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $testDb->exec("CREATE TABLE roles (id INT PRIMARY KEY, role_name VARCHAR(50) NOT NULL UNIQUE) ENGINE=InnoDB");
    $testDb->exec("CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, verification_code VARCHAR(10) NULL) ENGINE=InnoDB");
    $testDb->exec("CREATE TABLE admin_users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(150) NOT NULL, role_id INT NOT NULL, status ENUM('active','inactive') NOT NULL) ENGINE=InnoDB");
    $testDb->exec("INSERT INTO roles VALUES (1,'super_admin')");
    $testDb->exec("INSERT INTO admin_users (email,role_id,status) VALUES ('admin@vuka.local',1,'active'),('operator@example.test',1,'active')");

    $environment = getenv();
    $environment['DB_DSN'] = $testDsn;
    $environment['DB_USER'] = DB_USER;
    $environment['DB_PASS'] = DB_PASS;
    $runScript = static function (string $script) use ($environment) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), $environment);
        if (!is_resource($process)) return [127, '', 'Could not start migration script'];
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        return [proc_close($process), $stdout, $stderr];
    };

    $run = $runScript('scripts/migrate.php');
    $column = $testDb->query("SHOW COLUMNS FROM users LIKE 'verification_code'")->fetch(PDO::FETCH_ASSOC);
    $seedStatus = $testDb->query("SELECT status FROM admin_users WHERE email='admin@vuka.local'")->fetchColumn();
    $otherStatus = $testDb->query("SELECT status FROM admin_users WHERE email='operator@example.test'")->fetchColumn();
    $record('Migration widens the verification hash column', $run[0] === 0 && isset($column['Type']) && stripos($column['Type'], 'varchar(255)') !== false, $run[0] === 0 ? trim($run[1]) : 'migration command failed');
    $record('Migration disables only the original seeded administrator and preserves its row', $seedStatus === 'inactive' && $otherStatus === 'active', 'seed account=' . (string)$seedStatus . ', unrelated account=' . (string)$otherStatus);

    $again = $runScript('scripts/migrate.php');
    $record('Migration runner is idempotent', $again[0] === 0 && str_contains($again[1], 'already applied'), trim($again[1]));

    $down = $runScript('scripts/rollback-001.php');
    $legacyColumn = $testDb->query("SHOW COLUMNS FROM users LIKE 'verification_code'")->fetch(PDO::FETCH_ASSOC);
    $record('Rollback restores legacy column only before hashes exist', $down[0] === 0 && isset($legacyColumn['Type']) && stripos($legacyColumn['Type'], 'varchar(10)') !== false, trim($down[1]));

    $reapply = $runScript('scripts/migrate.php');
    $testDb->exec("INSERT INTO users (verification_code) VALUES ('" . password_hash('123456', PASSWORD_DEFAULT) . "')");
    $refusedDown = $runScript('scripts/rollback-001.php');
    $stillWide = $testDb->query("SHOW COLUMNS FROM users LIKE 'verification_code'")->fetch(PDO::FETCH_ASSOC);
    $record('Rollback refuses to truncate stored verification hashes', $reapply[0] === 0 && $refusedDown[0] !== 0 && isset($stillWide['Type']) && stripos($stillWide['Type'], 'varchar(255)') !== false, trim($refusedDown[1] . $refusedDown[2]));
} catch (Throwable $e) {
    $record('Disposable migration database setup and execution', false, 'Migration test failed without printing connection details.');
} finally {
    if (isset($testDb)) $testDb = null;
    if ($created) {
        try {
            $pdo->exec('DROP DATABASE `' . $databaseName . '`');
        } catch (Throwable $e) {
            $record('Disposable database cleanup', false, 'Remove the uniquely named test database manually after review.');
        }
    }
}

$failed = 0;
foreach ($tests as [$name, $ok, $detail]) {
    if (!$ok) $failed++;
    fwrite(STDOUT, ($ok ? 'PASS' : 'FAIL') . ' ' . $name . ($detail !== '' ? ' — ' . $detail : '') . "\n");
}
fwrite(STDOUT, 'TOTAL: ' . (count($tests) - $failed) . ' passed, ' . $failed . " failed\n");
exit($failed === 0 ? 0 : 1);
?>
