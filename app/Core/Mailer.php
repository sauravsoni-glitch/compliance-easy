<?php

namespace App\Core;

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

        $subject = 'You have been invited to Easy Home Finance';
        [$htmlBody, $altBody] = self::inviteBody($toName, $inviteLink, $roleLabel, $department);
        return self::sendViaMailgun($cfg, $appConfig, $toEmail, $toName, $subject, $htmlBody, $altBody);
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
            self::logMailgun($cfg, [
                'ok' => false,
                'error' => 'Mailgun requires PHP curl extension',
            ]);
            return [false, 'Mailgun requires PHP curl extension'];
        }
        $domain = trim((string) ($cfg['mailgun_domain'] ?? ''));
        $apiKey = trim((string) ($cfg['mailgun_api_key'] ?? ''));
        $endpoint = rtrim((string) ($cfg['mailgun_endpoint'] ?? 'https://api.mailgun.net'), '/');
        $authUser = Auth::user();
        $fromEmail = trim((string) ($authUser['email'] ?? ''));
        if ($domain === '' || $apiKey === '' || $fromEmail === '') {
            self::logMailgun($cfg, [
                'ok' => false,
                'error' => 'MAILGUN_DOMAIN, MAILGUN_API_KEY, and logged-in user email must be set',
                'from' => $fromEmail,
                'to' => $toEmail,
                'subject' => $subject,
            ]);
            return [false, 'MAILGUN_DOMAIN, MAILGUN_API_KEY, and logged-in user email must be set'];
        }

        $to = $toName !== '' ? $toName . ' <' . $toEmail . '>' : $toEmail;
        $fromName = trim((string) ($cfg['from_name'] ?? 'Easy Home Finance'));
        $from = $fromName !== '' ? $fromName . ' <' . $fromEmail . '>' : $fromEmail;
        $url = $endpoint . '/v3/' . rawurlencode($domain) . '/messages';

        $ch = curl_init($url);
        if ($ch === false) {
            self::logMailgun($cfg, [
                'ok' => false,
                'error' => 'Could not initialize Mailgun request',
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'url' => $url,
            ]);
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
            self::logMailgun($cfg, [
                'ok' => false,
                'error' => 'Mailgun request failed: ' . $curlErr,
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'url' => $url,
                'http_status' => $status,
            ]);
            return [false, 'Mailgun request failed: ' . $curlErr];
        }
        if ($status < 200 || $status >= 300) {
            $body = trim((string) $raw);
            if (strlen($body) > 350) {
                $body = substr($body, 0, 350) . '...';
            }
            self::logMailgun($cfg, [
                'ok' => false,
                'error' => 'Mailgun HTTP ' . $status,
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'url' => $url,
                'http_status' => $status,
                'response' => $body,
            ]);

            return [false, 'Mailgun HTTP ' . $status . ': ' . $body];
        }

        self::logMailgun($cfg, [
            'ok' => true,
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'url' => $url,
            'http_status' => $status,
            'response' => trim((string) $raw),
        ]);

        return [true, null];
    }

    private static function logMailgun(array $cfg, array $event): void
    {
        $path = (string) ($cfg['mailgun_log_path'] ?? '');
        if ($path === '') {
            $path = dirname(__DIR__, 2) . '/storage/logs/mailgun.log';
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = [
            'ts' => date('c'),
            'event' => 'mailgun_send',
        ] + $event;

        @file_put_contents($path, json_encode($line, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
    }
}
