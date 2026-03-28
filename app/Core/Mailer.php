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
        if (!class_exists(PHPMailer::class)) {
            return [false, 'PHPMailer not installed. Run: composer install'];
        }
        $from = trim((string) ($cfg['from_email'] ?? ''));
        $user = trim((string) ($cfg['username'] ?? ''));
        $pass = (string) ($cfg['password'] ?? '');
        if ($from === '' || $user === '' || $pass === '') {
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
            $mail->Subject = 'You have been invited to Easy Home Finance';
            $safeName = htmlspecialchars($toName !== '' ? $toName : 'there', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $safeLink = htmlspecialchars($inviteLink, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $safeRole = htmlspecialchars($roleLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $deptLine = $department !== ''
                ? '<p><strong>Department:</strong> ' . htmlspecialchars($department, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>'
                : '';
            $mail->Body = '<p>Hi ' . $safeName . ',</p>'
                . '<p>You have been invited to collaborate on <strong>Easy Home Finance</strong>.</p>'
                . '<p>Your role: <strong>' . $safeRole . '</strong></p>'
                . $deptLine
                . '<p><a href="' . $safeLink . '" style="display:inline-block;padding:12px 20px;background:#111827;color:#fff;text-decoration:none;border-radius:8px;">Join workspace</a></p>'
                . '<p style="color:#6b7280;font-size:14px;">Or copy this link:<br>' . $safeLink . '</p>'
                . '<p style="color:#6b7280;font-size:14px;">This link expires in 24 hours.</p>';
            $mail->AltBody = "Hi,\n\nYou have been invited to Easy Home Finance.\nRole: {$roleLabel}\n\nJoin: {$inviteLink}\n";
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
}
