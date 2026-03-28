<?php
/**
 * Quick SMTP test (Gmail or any server in config/mail.php + env vars).
 *
 * Usage (PowerShell, from project root):
 *   $env:MAIL_ENABLED="1"
 *   $env:MAIL_USERNAME="you@gmail.com"
 *   $env:MAIL_PASSWORD="xxxx xxxx xxxx xxxx"
 *   $env:MAIL_FROM="you@gmail.com"
 *   C:\xampp\php\php.exe scripts\test-smtp.php your-inbox@gmail.com
 */
$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php. Run: composer install\n");
    exit(1);
}
require $autoload;

$to = $argv[1] ?? '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/test-smtp.php recipient@example.com\n");
    exit(1);
}

$cfg = require $root . '/config/mail.php';
if (empty($cfg['enabled'])) {
    fwrite(STDERR, "MAIL_ENABLED is not set to 1. Set env MAIL_ENABLED=1 first.\n");
    exit(1);
}

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;

$from = trim((string) ($cfg['from_email'] ?? ''));
$user = trim((string) ($cfg['username'] ?? ''));
$pass = (string) ($cfg['password'] ?? '');
if ($from === '' || $user === '' || $pass === '') {
    fwrite(STDERR, "Set MAIL_FROM, MAIL_USERNAME, MAIL_PASSWORD.\n");
    exit(1);
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
    $mail->addAddress($to);
    $mail->Subject = 'SMTP test — Easy Home Finance';
    $mail->Body = '<p>If you see this, SMTP from the compliance app is working.</p>';
    $mail->AltBody = 'If you see this, SMTP from the compliance app is working.';
    $mail->send();
    echo "OK: test email sent to {$to}\n";
    exit(0);
} catch (PhpMailerException $e) {
    fwrite(STDERR, 'FAIL: ' . $mail->ErrorInfo . "\n");
    exit(1);
}
