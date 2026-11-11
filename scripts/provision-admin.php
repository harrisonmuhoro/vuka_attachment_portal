<?php
/** Provision the first active Super Admin without a source-controlled password. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/password-policy.php';
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    fwrite(STDERR, "Administrator provisioning requires MySQL/MariaDB.\n");
    exit(2);
}

$read = static function (string $label): string {
    fwrite(STDOUT, $label . ': ');
    return trim((string)fgets(STDIN));
};
$nationalId = $read('Administrator National ID (6-9 digits)');
$pfNumber = $read('Administrator PF number');
$fullName = $read('Full name');
$email = strtolower($read('Email address'));
$password = $read('Initial password (12 or more characters)');
$confirm = $read('Confirm initial password');
if (!preg_match('/^\d{6,9}$/', $nationalId) || $pfNumber === '' || strlen($pfNumber) > 50
    || $fullName === '' || mb_strlen($fullName) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || PasswordPolicy::validationError($password) !== null || $password !== $confirm) {
    fwrite(STDERR, "Input validation failed; no account was created.\n");
    exit(2);
}

try {
    $pdo->beginTransaction();
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'super_admin' LIMIT 1 FOR UPDATE");
    $roleStmt->execute();
    $roleId = (int)$roleStmt->fetchColumn();
    if ($roleId <= 0) throw new RuntimeException('Super Admin role is missing');
    $count = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE role_id = ? AND status = 'active'");
    $count->execute([$roleId]);
    if ((int)$count->fetchColumn() > 0) {
        $pdo->rollBack();
        fwrite(STDERR, "An active Super Admin already exists. Use the authenticated account-management workflow instead.\n");
        exit(1);
    }
    $insert = $pdo->prepare("INSERT INTO admin_users
        (national_id, pf_number, full_name, email, password_hash, department, department_id, role_id, status)
        VALUES (?, ?, ?, ?, ?, 'ALL', NULL, ?, 'active')");
    $insert->execute([$nationalId, $pfNumber, $fullName, $email, PasswordPolicy::hash($password), $roleId]);
    $pdo->commit();
    fwrite(STDOUT, "Super Admin provisioned. Store the password in an approved password manager.\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Initial administrator provisioning failed.');
    fwrite(STDERR, "Provisioning failed; no database details were printed. Confirm identifiers are unique and retry.\n");
    exit(1);
}
