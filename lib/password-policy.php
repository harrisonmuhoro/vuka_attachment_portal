<?php
/** Shared policy for newly set account passwords and safe hash upgrades. */

final class PasswordPolicy
{
    public const MIN_CHARACTERS = 12;
    public const MAX_INPUT_BYTES = 4096;
    private static ?bool $defaultIsBcrypt = null;

    /** Return null for valid input, otherwise a user-safe validation message. */
    public static function validationError(string $password): ?string
    {
        if (mb_strlen($password, 'UTF-8') < self::MIN_CHARACTERS) {
            return 'Password must be at least 12 characters.';
        }
        if (strlen($password) > self::MAX_INPUT_BYTES) {
            return 'Password is too long.';
        }
        // PHP's current PASSWORD_DEFAULT uses bcrypt, whose input limit is 72 bytes.
        // Reject excess bytes to prevent distinct long passphrases hashing identically.
        if (self::usesBcryptDefault() && strlen($password) > 72) {
            return 'Password is too long for the configured password hashing algorithm.';
        }
        return null;
    }

    public static function hash(string $password): string
    {
        $error = self::validationError($password);
        if ($error !== null) throw new InvalidArgumentException($error);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) throw new RuntimeException('Password hashing failed');
        return $hash;
    }

    /** Upgrade outdated hashes opportunistically after a successful login. */
    public static function rehashIfNeeded(PDO $pdo, string $table, int $id, string $password, string $hash): void
    {
        if (!in_array($table, ['users', 'admin_users'], true) || $id <= 0
            || !password_needs_rehash($hash, PASSWORD_DEFAULT)) return;
        try {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            if (is_string($newHash)) {
                $pdo->prepare("UPDATE {$table} SET password_hash = ? WHERE id = ?")->execute([$newHash, $id]);
            }
        } catch (Throwable $e) {
            error_log('Password hash upgrade failed; authentication remains valid.');
        }
    }

    private static function usesBcryptDefault(): bool
    {
        if (self::$defaultIsBcrypt === null) {
            $probe = password_hash('vuka-policy-probe', PASSWORD_DEFAULT);
            self::$defaultIsBcrypt = $probe !== false && (password_get_info($probe)['algoName'] ?? '') === 'bcrypt';
        }
        return self::$defaultIsBcrypt;
    }
}
