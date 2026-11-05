<?php
/**
 * Secure SMTP mail transport.
 * Credentials are read from environment variables (or pre-defined constants),
 * never embedded in source files.
 */
final class JustMMailer
{
    public static function sendPasswordResetCode(string $recipient, string $code): bool
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            error_log('Just-M mailer: invalid recipient address.');
            return false;
        }

        $value = static function (string $constant, string $environment, $default = null) {
            if (defined($constant)) {
                return constant($constant);
            }
            $env = getenv($environment);
            return ($env === false || $env === '') ? $default : $env;
        };

        $host = trim((string)$value('SMTP_HOST', 'JUSTM_SMTP_HOST', ''));
        $username = (string)$value('SMTP_USERNAME', 'JUSTM_SMTP_USERNAME', '');
        $password = (string)$value('SMTP_PASSWORD', 'JUSTM_SMTP_PASSWORD', '');
        $fromEmail = trim((string)$value('SMTP_FROM_EMAIL', 'JUSTM_SMTP_FROM_EMAIL', $username));
        $fromName = trim((string)$value('SMTP_FROM_NAME', 'JUSTM_SMTP_FROM_NAME', 'Just-M'));
        $port = (int)$value('SMTP_PORT', 'JUSTM_SMTP_PORT', 587);
        $encryption = strtolower(trim((string)$value('SMTP_ENCRYPTION', 'JUSTM_SMTP_ENCRYPTION', 'tls')));

        if ($host === '' || $fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
            || $port < 1 || $port > 65535 || !in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            error_log('Just-M mailer: SMTP is not configured or configuration is invalid.');
            return false;
        }

        try {
            $autoload = dirname(__DIR__, 2) . '/plugins/PHPMailer/vendor/autoload.php';
            if (!is_file($autoload)) {
                throw new RuntimeException('PHPMailer autoloader is missing.');
            }
            require_once $autoload;

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = ($username !== '');
            if ($mail->SMTPAuth) {
                $mail->Username = $username;
                $mail->Password = $password;
            }
            if ($encryption === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $mail->Timeout = 15;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($recipient);
            $mail->isHTML(true);
            $mail->Subject = 'إعادة تعيين كلمة المرور';
            $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $mail->Body = '<div dir="rtl" style="font-family:Arial,sans-serif">'
                . '<p>رمز التحقق لإعادة تعيين كلمة المرور هو:</p>'
                . '<h2 style="letter-spacing:4px">' . $safeCode . '</h2>'
                . '<p>إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذه الرسالة.</p></div>';
            $mail->AltBody = 'رمز التحقق لإعادة تعيين كلمة المرور: ' . $code;
            $mail->send();
            return true;
        } catch (Throwable $e) {
            // Do not log SMTP passwords or message contents.
            error_log('Just-M mailer failed: ' . get_class($e) . ' - ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('sendEmail')) {
    function sendEmail($email, $code): bool
    {
        return JustMMailer::sendPasswordResetCode((string)$email, (string)$code);
    }
}
