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
        [$htmlBody, $altBody] = self::inviteBody($toEmail, $toName, $inviteLink, $roleLabel, $department);
        return self::sendViaMailgun($cfg, $appConfig, $toEmail, $toName, $subject, $htmlBody, $altBody);
    }

    /**
     * Notify owner / reviewer / approver after a compliance is created (Mailgun).
     * Sends one message per recipient; failures are logged only (does not throw).
     *
     * @param array<int, array{email: string, name: string}> $recipients Distinct recipients (email => ...)
     * @return array{ok: int, fail: int, results: list<array{email: string, name: string, ok: bool, error: ?string}>}
     */
    public static function sendComplianceCreatedToRecipients(
        array $appConfig,
        array $recipients,
        string $subject,
        string $plainBody,
        ?string $htmlBody = null
    ): array {
        return self::sendNotificationToRecipients($appConfig, $recipients, $subject, $plainBody, [], $htmlBody);
    }

    /**
     * Generic notification sender (Mailgun) for multiple recipients.
     *
     * @param array<int, array{email: string, name: string}> $recipients
     * @param list<array{email: string, name?: string}> $ccRecipients Used only when there is exactly one primary recipient (e.g. reminder to owner with CC).
     * @return array{ok: int, fail: int, results: list<array{email: string, name: string, ok: bool, error: ?string}>}
     */
    public static function sendNotificationToRecipients(
        array $appConfig,
        array $recipients,
        string $subject,
        string $plainBody,
        array $ccRecipients = [],
        ?string $htmlBody = null
    ): array {
        $cfg = self::config();
        $results = [];
        $ok = 0;
        $fail = 0;
        if ($recipients === []) {
            return ['ok' => 0, 'fail' => 0, 'results' => []];
        }
        if (empty($cfg['enabled'])) {
            foreach ($recipients as $r) {
                $email = trim((string) ($r['email'] ?? ''));
                $name = trim((string) ($r['name'] ?? ''));
                $results[] = ['email' => $email, 'name' => $name, 'ok' => false, 'error' => 'Mail is disabled in config'];
                $fail++;
            }

            return ['ok' => $ok, 'fail' => $fail, 'results' => $results];
        }
        $htmlOut = $htmlBody ?? (
            '<div style="font-family:system-ui,Segoe UI,Roboto,sans-serif;font-size:15px;line-height:1.5;color:#111827;">'
            . nl2br(htmlspecialchars($plainBody, ENT_QUOTES | ENT_HTML5, 'UTF-8'), false)
            . '</div>'
        );
        $ccHeader = '';
        if (count($recipients) === 1 && $ccRecipients !== []) {
            $ccHeader = self::formatCcHeader($ccRecipients);
        }
        foreach ($recipients as $r) {
            $email = trim((string) ($r['email'] ?? ''));
            $name = trim((string) ($r['name'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results[] = ['email' => $email, 'name' => $name, 'ok' => false, 'error' => 'Invalid or missing email'];
                $fail++;
                continue;
            }
            [$sent, $err] = self::sendViaMailgun($cfg, $appConfig, $email, $name, $subject, $htmlOut, $plainBody, $ccHeader);
            if ($sent) {
                $ok++;
                $results[] = ['email' => $email, 'name' => $name, 'ok' => true, 'error' => null];
            } else {
                $fail++;
                $results[] = ['email' => $email, 'name' => $name, 'ok' => false, 'error' => $err];
            }
        }

        return ['ok' => $ok, 'fail' => $fail, 'results' => $results];
    }

    /**
     * Workspace invite: form-style HTML + plain text (email-client friendly tables).
     *
     * @return array{0:string,1:string}
     */
    private static function inviteBody(string $toEmail, string $toName, string $inviteLink, string $roleLabel, string $department): array
    {
        $safeName = htmlspecialchars($toName !== '' ? $toName : 'there', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeEmail = htmlspecialchars($toEmail, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeLink = htmlspecialchars($inviteLink, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeRole = htmlspecialchars($roleLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $deptVal = $department !== '' ? htmlspecialchars($department, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '<span style="color:#9ca3af;">—</span>';
        $deptPlain = $department !== '' ? $department : '—';

        $row = static function (string $label, string $valueHtml): string {
            return '<tr>'
                . '<td style="padding:12px 16px;width:38%;font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;vertical-align:top;border-top:1px solid #e5e7eb;background:#fafafa;">' . $label . '</td>'
                . '<td style="padding:12px 16px;font-size:15px;color:#111827;font-weight:600;vertical-align:top;border-top:1px solid #e5e7eb;">' . $valueHtml . '</td>'
                . '</tr>';
        };

        $inner = '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;font-family:Segoe UI,system-ui,Roboto,Helvetica,Arial,sans-serif;">
  <tr>
    <td style="background:linear-gradient(135deg,#1f2937 0%,#111827 50%,#7f1d1d 100%);border-radius:14px 14px 0 0;padding:26px 24px;color:#fff;">
      <div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;opacity:0.88;">Workspace invitation</div>
      <h1 style="margin:10px 0 4px;font-size:21px;line-height:1.3;font-weight:700;">You&rsquo;re invited to Easy Home Finance</h1>
      <p style="margin:0;font-size:14px;opacity:0.9;line-height:1.5;">Hi ' . $safeName . ', use the secure link below to finish setup and access the <strong>compliance workspace</strong>.</p>
    </td>
  </tr>
  <tr>
    <td style="background:#fff;padding:0;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 14px 14px;overflow:hidden;">
      <div style="padding:18px 20px 8px;font-size:11px;font-weight:700;letter-spacing:0.06em;color:#6b7280;text-transform:uppercase;">Invitation details <span style="font-weight:600;color:#9ca3af;">(read-only summary)</span></div>
      <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
        ' . $row('Product', '<span style="font-weight:600;">Easy Home Finance</span> <span style="color:#6b7280;font-weight:500;">— Compliance &amp; reporting</span>') . '
        ' . $row('Invitee name', $safeName) . '
        ' . $row('Invitee email', '<span style="word-break:break-all;">' . $safeEmail . '</span>') . '
        ' . $row('Assigned role', $safeRole . ' <span style="display:block;margin-top:6px;font-size:13px;font-weight:500;color:#6b7280;">Controls what you can do with compliance items (create, review, approve, admin).</span>') . '
        ' . $row('Department', $deptVal) . '
        ' . $row('Link valid for', '<span style="color:#b91c1c;font-weight:700;">24 hours</span> <span style="color:#6b7280;font-weight:500;">from when this email was sent</span>') . '
      </table>
      <div style="padding:22px 20px 10px;text-align:center;">
        <a href="' . $safeLink . '" style="display:inline-block;padding:14px 28px;background:#111827;color:#fff !important;text-decoration:none;border-radius:10px;font-size:15px;font-weight:700;letter-spacing:0.02em;">Join workspace</a>
      </div>
      <div style="padding:0 20px 22px;">
        <p style="margin:0 0 8px;font-size:12px;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;">Or copy invitation link</p>
        <p style="margin:0;padding:12px 14px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;word-break:break-all;color:#374151;line-height:1.5;">' . $safeLink . '</p>
      </div>
      <div style="padding:14px 20px 20px;background:#f3f4f6;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;line-height:1.55;">
        If you did not expect this invitation, you can ignore this message. For help, contact your organization administrator.
      </div>
    </td>
  </tr>
</table>';

        $html = '<div style="background:#f3f4f6;padding:24px 12px;">' . $inner . '</div>';

        $text = "WORKSPACE INVITATION — Easy Home Finance (Compliance)\n"
            . str_repeat('=', 48) . "\n\n"
            . 'Hi ' . ($toName !== '' ? $toName : 'there') . ",\n\n"
            . "You've been invited to the compliance workspace.\n\n"
            . "INVITATION DETAILS\n"
            . "- Invitee name: " . ($toName !== '' ? $toName : '—') . "\n"
            . "- Invitee email: {$toEmail}\n"
            . "- Assigned role: {$roleLabel}\n"
            . "- Department: {$deptPlain}\n"
            . "- Link expires: 24 hours from send\n\n"
            . "JOIN (open in browser):\n{$inviteLink}\n\n"
            . "If you did not expect this, ignore this email.\n";

        return [$html, $text];
    }

    /**
     * @return array{0:bool,1:?string}
     */
    /**
     * @param list<array{email: string, name?: string}> $ccRecipients
     */
    private static function formatCcHeader(array $ccRecipients): string
    {
        $parts = [];
        foreach ($ccRecipients as $c) {
            $em = trim((string) ($c['email'] ?? ''));
            if ($em === '' || !filter_var($em, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $nm = trim((string) ($c['name'] ?? ''));
            $parts[] = $nm !== '' ? $nm . ' <' . $em . '>' : $em;
        }

        return implode(', ', $parts);
    }

    private static function sendViaMailgun(
        array $cfg,
        array $appConfig,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $altBody,
        string $ccHeader = ''
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
        $fromEmail = trim((string) ($cfg['from_email'] ?? ''));
        if ($fromEmail === '') {
            $authUser = Auth::user();
            $fromEmail = trim((string) ($authUser['email'] ?? ''));
        }
        if ($domain === '' || $apiKey === '' || $fromEmail === '') {
            self::logMailgun($cfg, [
                'ok' => false,
                'error' => 'MAILGUN_DOMAIN, MAILGUN_API_KEY, and From email (MAIL_FROM / from_email or logged-in user) must be set',
                'from' => $fromEmail,
                'to' => $toEmail,
                'subject' => $subject,
            ]);
            return [false, 'MAILGUN_DOMAIN, MAILGUN_API_KEY, and From email must be set'];
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
        $post = [
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $altBody,
        ];
        if ($ccHeader !== '') {
            $post['cc'] = $ccHeader;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
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
