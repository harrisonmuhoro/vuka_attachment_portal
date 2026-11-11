<?php
/**
 * Mailer wrapper — Vuka Portal (Feature #1)
 *
 * Sends HTML email via SMTP (PHPMailer) when configured. If SMTP credentials
 * are blank OR PHPMailer is not vendored, delivery fails closed without
 * writing message content or credential-bearing links to a project log.
 *
 * To enable real sending: set SMTP_USER / SMTP_PASS in config.php and drop
 * PHPMailer into lib/phpmailer/ (PHPMailer.php, SMTP.php, Exception.php).
 */

require_once __DIR__ . '/../config/config.php';

/**
 * @return bool true if sent (or logged in fallback mode), false on hard failure.
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $smtpConfigured = defined('SMTP_USER') && defined('SMTP_PASS')
        && SMTP_USER !== '' && SMTP_PASS !== '';

    // Attempt to load a vendored PHPMailer if present.
    $phpmailerAvailable = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
    if (!$phpmailerAvailable) {
        $vendor = __DIR__ . '/phpmailer/PHPMailer.php';
        if (is_file($vendor)) {
            require_once __DIR__ . '/phpmailer/Exception.php';
            require_once __DIR__ . '/phpmailer/PHPMailer.php';
            require_once __DIR__ . '/phpmailer/SMTP.php';
            $phpmailerAvailable = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
        }
    }

    // LOG-ONLY fallback.
    if (!$smtpConfigured || !$phpmailerAvailable) {
        return logMailFallback($toEmail, $toName, $subject, $htmlBody,
            !$smtpConfigured ? 'SMTP not configured' : 'PHPMailer not installed');
    }

    try {
        $mailClass = 'PHPMailer\\PHPMailer\\PHPMailer';
        $mail = new $mailClass(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = 'tls'; // STARTTLS
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_FROM, defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Vuka Portal');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail delivery failed.');
        return logMailFallback($toEmail, $toName, $subject, $htmlBody, 'send failed: ' . $e->getMessage());
    }
}

/**
 * Record only a sanitized delivery failure outside the document root.
 */
function logMailFallback(string $toEmail, string $toName, string $subject, string $htmlBody, string $reason): bool {
    $configuredDir = getenv('VUKA_LOG_DIR');
    $logDir = $configuredDir !== false && $configuredDir !== ''
        ? $configuredDir
        : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'vuka-portal-logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0700, true);
    }
    if (!is_dir($logDir) || !is_writable($logDir)) {
        error_log('Mail delivery unavailable; private log directory is not writable.');
        return false;
    }
    $entry = '[' . date('Y-m-d H:i:s') . "] Mail delivery unavailable; message content omitted.\n";
    $written = @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'mail.log', $entry, FILE_APPEND | LOCK_EX);
    if ($written !== false) @chmod($logDir . DIRECTORY_SEPARATOR . 'mail.log', 0600);
    return false;
}
