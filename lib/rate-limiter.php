<?php
/**
 * Login Rate Limiter — Vuka Portal (Feature #13)
 *
 * Tracks failed login attempts per IP + endpoint in the `login_attempts` table.
 * After MAX_FAILURES within the sliding window, the IP is locked out.
 *
 * Usage in a login endpoint:
 *   require_once __DIR__ . '/../lib/rate-limiter.php';
 *   checkRateLimit($pdo, 'login');                 // call BEFORE verifying credentials
 *   ... on failed auth:   recordLoginAttempt($pdo, 'login', false, $identifier);
 *   ... on success:       clearLoginAttempts($pdo, 'login'); recordLoginAttempt($pdo, 'login', true, $identifier);
 */

if (!defined('RATE_LIMIT_MAX_FAILURES')) {
    define('RATE_LIMIT_MAX_FAILURES', 5);      // failures allowed in the window
    define('RATE_LIMIT_WINDOW_MIN', 15);       // sliding window (minutes)
}

/**
 * Resolve the client IP consistently (mirrors SessionManager::getClientIP).
 */
function rl_client_ip(): string {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Reject the request (HTTP 429) if too many recent failures from this IP+endpoint.
 * Emits JSON via json_response() and exits when locked out.
 */
function checkRateLimit(PDO $pdo, string $endpoint): void {
    try {
        // Opportunistic cleanup so the table never bloats (older than 24h).
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

        $ip     = rl_client_ip();
        $window = date('Y-m-d H:i:s', time() - (RATE_LIMIT_WINDOW_MIN * 60));

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts
                               WHERE ip_address = ? AND endpoint = ?
                               AND success = 0 AND attempted_at > ?");
        $stmt->execute([$ip, $endpoint, $window]);
        $failures = (int) $stmt->fetchColumn();

        if ($failures >= RATE_LIMIT_MAX_FAILURES) {
            if (function_exists('json_response')) {
                if (!headers_sent()) { http_response_code(429); }
                json_response(false, ['retry_after' => RATE_LIMIT_WINDOW_MIN * 60],
                    'Too many failed attempts. Please try again in ' . RATE_LIMIT_WINDOW_MIN . ' minutes.');
            } else {
                http_response_code(429);
                echo json_encode(['success' => false, 'error' => 'Too many failed attempts.']);
                exit;
            }
        }
    } catch (Exception $e) {
        // Never let the limiter itself block logins on infrastructure error.
        error_log('Rate limiter check error: ' . $e->getMessage());
    }
}

/**
 * Record a login attempt (success or failure).
 */
function recordLoginAttempt(PDO $pdo, string $endpoint, bool $success, ?string $identifier = null): void {
    try {
        $pdo->prepare("INSERT INTO login_attempts (ip_address, endpoint, identifier, success)
                       VALUES (?, ?, ?, ?)")
            ->execute([rl_client_ip(), $endpoint, $identifier, $success ? 1 : 0]);
    } catch (Exception $e) {
        error_log('Rate limiter record error: ' . $e->getMessage());
    }
}

/**
 * Clear this IP's failed attempts for an endpoint after a successful login.
 */
function clearLoginAttempts(PDO $pdo, string $endpoint): void {
    try {
        $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND endpoint = ? AND success = 0")
            ->execute([rl_client_ip(), $endpoint]);
    } catch (Exception $e) {
        error_log('Rate limiter clear error: ' . $e->getMessage());
    }
}
