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
        MailIstTime::ensureDefaultTimezone($appConfig);
        $cfg = self::config();
        if (empty($cfg['enabled'])) {
            return [false, null];
        }

        $subject = 'You have been invited to Easy Home Finance';
        [$htmlBody, $altBody] = self::inviteBody($toEmail, $toName, $inviteLink, $roleLabel, $department);
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
     * Generic single-recipient mail sender used by automations.
     *
     * @return array{0:bool,1:?string} [sent, errorMessage]
     */
    public static function sendGeneric(
        array $appConfig,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $altBody,
        array $ccEmails = []
    ): array {
        MailIstTime::ensureDefaultTimezone($appConfig);
        $cfg = self::config();
        if (empty($cfg['enabled'])) {
            return [false, null];
        }
        $provider = strtolower((string) ($cfg['provider'] ?? 'smtp'));
        if ($provider === 'mailgun') {
            return self::sendViaMailgun($cfg, $appConfig, $toEmail, $toName, $subject, $htmlBody, $altBody, $ccEmails);
        }
        if ($provider === 'auto') {
            [$ok, $err] = self::sendViaMailgun($cfg, $appConfig, $toEmail, $toName, $subject, $htmlBody, $altBody, $ccEmails);
            if ($ok) {
                return [true, null];
            }

            return self::sendViaSmtp($cfg, $appConfig, $toEmail, $toName, $subject, $htmlBody, $altBody, $err, $ccEmails);
        }

        return self::sendViaSmtp($cfg, $appConfig, $toEmail, $toName, $subject, $htmlBody, $altBody, null, $ccEmails);
    }

    /**
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
      <div style="font-size:24px;line-height:1;font-weight:800;letter-spacing:0.01em;color:#ef4444;margin-bottom:10px;text-transform:lowercase;">easy</div>
      <div style="font-size:11px;letter-spacing:0.14em;text-transform:uppercase;opacity:0.88;">Workspace invitation</div>
      <h1 style="margin:10px 0 4px;font-size:21px;line-height:1.3;font-weight:700;">You&rsquo;re invited to Easy Home Compliance Management system</h1>
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
    private static function sendViaSmtp(
        array $cfg,
        array $appConfig,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $altBody,
        ?string $mailgunError,
        array $ccEmails = []
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
            foreach ($ccEmails as $cc) {
                $cc = trim((string) $cc);
                if ($cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($cc);
                }
            }
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
        string $altBody,
        array $ccEmails = []
    ): array {
        if (!function_exists('curl_init')) {
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
            return [false, 'MAILGUN_DOMAIN, MAILGUN_API_KEY, and MAIL_FROM (or logged-in sender email) must be set'];
        }

        $to = $toName !== '' ? $toName . ' <' . $toEmail . '>' : $toEmail;
        $ccList = [];
        foreach ($ccEmails as $cc) {
            $cc = trim((string) $cc);
            if ($cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                $ccList[] = $cc;
            }
        }
        $fromName = trim((string) ($cfg['from_name'] ?? 'Easy Home Finance'));
        $from = $fromName !== '' ? $fromName . ' <' . $fromEmail . '>' : $fromEmail;
        $url = $endpoint . '/v3/' . rawurlencode($domain) . '/messages';
        $postFields = [
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => $altBody,
        ];
        if (!empty($ccList)) {
            $postFields['cc'] = implode(',', $ccList);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return [false, 'Could not initialize Mailgun request'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
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
