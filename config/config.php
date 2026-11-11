<?php
/**
 * Vuka application configuration bootstrap.
 * Reads local credentials from the project-root .env file and initializes PDO.
 */

if (!defined('VUKA_CONFIG_LOADED')) {
    define('VUKA_CONFIG_LOADED', true);

    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($value !== '' && strlen($value) >= 2) {
                $first = $value[0];
                $last = substr($value, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($name !== '' && getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }
    }

    $env = function ($key, $default = '') {
        $value = getenv($key);
        return $value === false ? $default : $value;
    };

    if (!defined('DB_HOST')) define('DB_HOST', $env('DB_HOST', 'localhost'));
    if (!defined('DB_PORT')) define('DB_PORT', (int) $env('DB_PORT', '3306'));
    if (!defined('DB_NAME')) define('DB_NAME', $env('DB_NAME', 'vuka_attachment_portal'));
    if (!defined('DB_USER')) define('DB_USER', $env('DB_USER', 'root'));
    if (!defined('DB_PASS')) define('DB_PASS', $env('DB_PASS', ''));
    if (!defined('APP_URL')) define('APP_URL', rtrim($env('APP_URL', 'http://localhost/vuka_attachment'), '/'));

    if (!defined('SMTP_HOST')) define('SMTP_HOST', $env('SMTP_HOST', ''));
    if (!defined('SMTP_PORT')) define('SMTP_PORT', (int) $env('SMTP_PORT', '587'));
    if (!defined('SMTP_USER')) define('SMTP_USER', $env('SMTP_USER', ''));
    if (!defined('SMTP_PASS')) {
        $smtpPassword = $env('SMTP_PASS', '');
        if ($smtpPassword === '') $smtpPassword = $env('RESEND_API_KEY', '');
        define('SMTP_PASS', $smtpPassword);
    }
    if (!defined('SMTP_FROM')) define('SMTP_FROM', $env('SMTP_FROM', ''));
    if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', $env('SMTP_FROM_NAME', 'Vuka Portal'));

    if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 2 * 1024 * 1024);
    if (!defined('ALLOWED_EXTENSIONS')) define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png']);
    if (!defined('ALLOWED_MIME_TYPES')) define('ALLOWED_MIME_TYPES', [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ]);

    /** Send a consistent JSON response and stop processing. */
    if (!function_exists('json_response')) {
        function json_response($success, $data = null, $message = null, $httpCode = null)
        {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            if ($httpCode !== null && !headers_sent()) {
                http_response_code((int) $httpCode);
            }
            $response = ['success' => (bool) $success];
            if ($data !== null) {
                // Keep the payload under `data` and expose its fields at the
                // top level too. Existing page scripts read fields such as
                // `redirect` and `message` directly, while newer callers use
                // `data.<field>`.
                $response['data'] = $data;
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        if ($key !== 'success' && $key !== 'data') {
                            $response[$key] = $value;
                        }
                    }
                }
            }
            if ($message !== null) $response['message'] = $message;
            echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if (!function_exists('hash_password')) {
        function hash_password($password)
        {
            return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        }
    }

    if (!function_exists('verify_password')) {
        function verify_password($password, $passwordHash)
        {
            return is_string($passwordHash) && password_verify($password, $passwordHash);
        }
    }

    if (!function_exists('get_bearer_token')) {
        function get_bearer_token()
        {
            $authorization = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? '';

            if ($authorization === '' && function_exists('getallheaders')) {
                $headers = getallheaders();
                foreach ($headers as $name => $value) {
                    if (strcasecmp($name, 'Authorization') === 0) {
                        $authorization = $value;
                        break;
                    }
                }
            }

            if (preg_match('/^Bearer\\s+(.+)$/i', trim($authorization), $matches)) {
                return trim($matches[1]);
            }
            return null;
        }
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $GLOBALS['pdo'] = $pdo;
    } catch (PDOException $exception) {
        error_log('Vuka database connection failed: ' . $exception->getMessage());
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Database connection failed. Check local configuration.']);
            exit;
        }
        throw $exception;
    }
}
