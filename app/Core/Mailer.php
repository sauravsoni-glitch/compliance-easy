<?php

namespace App\Core;

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    private static function config(): array
    {
        $path = dirname(__DIR__, 2) . '/config/mail.php';

        return is_file($path) ? (require $path) : ['enabled' => false];
    }

    /**
     * @return array{0:bool,1:?string} [sent, errorMessage]
     */
    public static function sendWorkspaceInvite(
        array $appConfig,
        string $toEmail,
        string $toName,
        string $inviteLink,
        string $roleLabel,
        string $department
    ): array {
        $cfg = self::config();
        if (empty($cfg['enabled'])) {
            return [false, null];
        }
        $from = trim((string) ($cfg['from_email'] ?? ''));
        if ($from === '') {
            return [false, 'MAIL_FROM must be set'];
        }

        $subject = 'You have been invited to Easy Home Finance';
        [$htmlBody, $altBody] = self::inviteBody($toName, $inviteLink, $roleLabel, $department);
        $provider = strtolower((string) ($cfg['provider'] ?? 'smtp'));

        if ($provider === 'mailgun') {
            return self::sendViaMailgun($cfg, $appConfig, $toEmail, $toName, $subject, $htmlBody, $altBody);
        }
        if ($provider === 'auto') {
            [$ok, $err] = self::sendViaMailgun($cfg, $appConfig, $toEmail, $toName, $subject, $htmlBody, $altBody);
            if ($ok) {
                return [true, null];
            }

            return self::sendViaSmtp($cfg, $appConfig, $toEmail, $toName, $subject, $htmlBody, $altBody, $err);
        }

        return self::sendViaSmtp($cfg, $appConfig, $toEmail, $toName, $subject, $htmlBody, $altBody, null);
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function inviteBody(string $toName, string $inviteLink, string $roleLabel, string $department): array
    {
        $safeName = htmlspecialchars($toName !== '' ? $toName : 'there', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeLink = htmlspecialchars($inviteLink, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeRole = htmlspecialchars($roleLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $deptLine = $department !== ''
            ? '<p><strong>Department:</strong> ' . htmlspecialchars($department, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>'
            : '';
        $html = '<p>Hi ' . $safeName . ',</p>'
            . '<p>You have been invited to collaborate on <strong>Easy Home Finance</strong>.</p>'
            . '<p>Your role: <strong>' . $safeRole . '</strong></p>'
            . $deptLine
            . '<p><a href="' . $safeLink . '" style="display:inline-block;padding:12px 20px;background:#111827;color:#fff;text-decoration:none;border-radius:8px;">Join workspace</a></p>'
            . '<p style="color:#6b7280;font-size:14px;">Or copy this link:<br>' . $safeLink . '</p>'
            . '<p style="color:#6b7280;font-size:14px;">This link expires in 24 hours.</p>';
        $text = "Hi,\n\nYou have been invited to Easy Home Finance.\nRole: {$roleLabel}\n\nJoin: {$inviteLink}\n";

        return [$html, $text];
    }

    /**
     * @return array{0:bool,1:?string}
     */
    private static function sendViaSmtp(
        array $cfg,
        array $appConfig,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $altBody,
        ?string $mailgunError
    ): array {
        if (!class_exists(PHPMailer::class)) {
            return [false, 'PHPMailer not installed. Run: composer install'];
        }
        $from = trim((string) ($cfg['from_email'] ?? ''));
        $user = trim((string) ($cfg['username'] ?? ''));
        $pass = preg_replace('/\s+/', '', (string) ($cfg['password'] ?? ''));
        if ($from === '' || $user === '' || $pass === '') {
            if ($mailgunError !== null) {
                return [false, 'Mailgun failed and SMTP credentials are missing. Mailgun error: ' . $mailgunError];
            }

            return [false, 'MAIL_FROM, MAIL_USERNAME, and MAIL_PASSWORD must be set'];
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) ($cfg['host'] ?? 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $enc = (string) ($cfg['encryption'] ?? 'tls');
            if ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port = (int) ($cfg['port'] ?? 587);
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($from, (string) ($cfg['from_name'] ?? 'Easy Home Finance'));
            $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody;
            $mail->send();

            return [true, null];
        } catch (PhpMailerException $e) {
            $msg = $mail->ErrorInfo ?: $e->getMessage();
            if (!empty($appConfig['debug'])) {
                error_log('Mailer::sendWorkspaceInvite: ' . $msg);
            }

            return [false, $msg];
        }
    }

    /**
     * @return array{0:bool,1:?string}
     */
    private static function sendViaMailgun(
        array $cfg,
        array $appConfig,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $altBody
    ): array {
        if (!function_exists('curl_init')) {
            return [false, 'Mailgun requires PHP curl extension'];
        }
        $domain = trim((string) ($cfg['mailgun_domain'] ?? ''));
        $apiKey = trim((string) ($cfg['mailgun_api_key'] ?? ''));
        $endpoint = rtrim((string) ($cfg['mailgun_endpoint'] ?? 'https://api.mailgun.net'), '/');
        $fromEmail = trim((string) ($cfg['from_email'] ?? ''));
        if ($domain === '' || $apiKey === '' || $fromEmail === '') {
            return [false, 'MAILGUN_DOMAIN, MAILGUN_API_KEY, and MAIL_FROM must be set'];
        }

        $to = $toName !== '' ? $toName . ' <' . $toEmail . '>' : $toEmail;
        $fromName = trim((string) ($cfg['from_name'] ?? 'Easy Home Finance'));
        $from = $fromName !== '' ? $fromName . ' <' . $fromEmail . '>' : $fromEmail;
        $url = $endpoint . '/v3/' . rawurlencode($domain) . '/messages';

        $ch = curl_init($url);
        if ($ch === false) {
            return [false, 'Could not initialize Mailgun request'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'html' => $htmlBody,
                'text' => $altBody,
            ],
            CURLOPT_USERPWD => 'api:' . $apiKey,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return [false, 'Mailgun request failed: ' . $curlErr];
        }
        if ($status < 200 || $status >= 300) {
            $body = trim((string) $raw);
            if (strlen($body) > 350) {
                $body = substr($body, 0, 350) . '...';
            }

            return [false, 'Mailgun HTTP ' . $status . ': ' . $body];
        }

        return [true, null];
    }
}
