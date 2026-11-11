<?php
/** Safe schema rollback; refuses to truncate hashed verification codes. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "This rollback requires MySQL/MariaDB.\n");
    exit(2);
}
$longCodes = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE CHAR_LENGTH(verification_code) > 10')->fetchColumn();
if ($longCodes > 0) {
    fwrite(STDERR, "Rollback refused: {$longCodes} verification hashes exceed the legacy column width. Restore a pre-migration backup to roll back safely.\n");
    exit(1);
}
$pdo->exec('ALTER TABLE users MODIFY verification_code VARCHAR(10) NULL');
$pdo->exec("DELETE FROM schema_migrations WHERE version = '001_phase1_security'");
fwrite(STDOUT, "Schema width restored. The seeded administrator remains inactive; do not reactivate it without setting a new password.\n");
