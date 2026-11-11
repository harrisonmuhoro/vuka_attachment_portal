<?php
/** Close approved vacancies past their deadline. Intended for a scheduled CLI run. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/vacancy-maintenance.php';
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "Vacancy maintenance requires MySQL/MariaDB.\n");
    exit(2);
}

try {
    $pdo->beginTransaction();
    $closed = closeExpiredVacancies($pdo);
    $pdo->commit();
    fwrite(STDOUT, "Closed {$closed} expired approved vacancy/vacancies.\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Expired vacancy maintenance failed.');
    fwrite(STDERR, "Expired vacancy maintenance failed.\n");
    exit(1);
}
